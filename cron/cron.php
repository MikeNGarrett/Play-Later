<?php
/*

Cron is intended to process the xml feed of new releases from Spotify and get the data into the database.
This should take care of all the heavy lifting and scheduled processing, so index.php only has to work on display.

*/
error_reporting(E_ALL);

include_once '../config.php';
$database = new Database();

$baseDir = dirname(realpath('./'));
$spotifyCountries = loadCountryCodesMap($baseDir.'/ISO-3166-1-alpha-2-country-codes-spotify.tsv');

function spotifyCountriesFilter($v) {
	global $spotifyCountries;
	return isset($spotifyCountries[strtoupper($v)]);
}

$date = date('Y-m-d');

$cachePath = check_dir( $baseDir.'/cache/' );
if ( !$cachePath ) {
	echo "Could not create cache directory";
	die;
}

$cachePath .= $date;

$albums = array();
$vaAlbums = array();
$fetchPageLimit = 5;
$totalResults = $startIndex = $itemsPerPage = 0;

function loadCountryCodesMap($path) {
	$map = array();
	$buf = file_get_contents($path);
	$buf = explode("\n", $buf);
	foreach ($buf as $line) {
		$line = explode("\t", $line);
		if (count($line) > 1 && strlen($line[0]) === 2) {
			$map[$line[0]] = $line[1];
		}
	}
	return $map;
}

function check_dir( $dir ) {
	if ( !file_exists( $dir ) ) {
		$check = mkdir( $dir, 0775, true ); // sets permissions and creates directories recursively
		if ( !$check ) {
			error_log( 'Could not create directory: ', $dir );
			return false;
		}
	}
	return $dir;
}

$allCountries = loadCountryCodesMap($baseDir.'/ISO-3166-1-alpha-2-country-codes.tsv');
$spotifyCountriesList = array_keys($spotifyCountries);

function countryCodeToName($xx) {
	global $allCountries;
	return isset($allCountries[$xx]) ? $allCountries[$xx] : $xx;
}

// xe = custom url encode
function xe($s) {
	static $u = array('&', '"', '<', '>');
	static $e = array('&#38;', '&#34;', '&#60;', '&#62;');
	return str_replace($u, $e, $s);
}

function firstChild($node, $nodeName) {
	$nl = $node->childNodes;
	for ($i=0;$i<min($nl->length, 4);$i++) {
		$n = $nl->item($i);
		if ($n->nodeType === XML_ELEMENT_NODE && $n->nodeName === $nodeName) {
			return $n;
		}
	}
}

function parseArtist($n) {
	return (object)array(
		'uri' => $n->getAttribute('href'),
		'name' => firstChild($n, 'name')->nodeValue,
	);
}

function parseId($n) {
	return (object)array(
		'type' => $n->getAttribute('type'),
		'id' => $n->nodeValue,
		'href' => $n->hasAttribute('href') ? $n->getAttribute('href') : null,
	);
}

function parseAvailability($n) {
	global $spotifyCountriesList;
	$terrs = array();
	$unrestricted = false;
	$nl = $n->childNodes;
	for ($i=0;$i<$nl->length;$i++) {
		$n2 = $nl->item($i);
		if ($n2->nodeName === 'territories') {
			if ($n2->nodeValue === 'worldwide')
				$unrestricted = true;
			else
				$terrs = explode(' ', $n2->nodeValue);
			break;
		}
	}
	$terrs = array_filter($terrs, 'spotifyCountriesFilter');
	if (!$unrestricted)
		$unrestricted = count($terrs) === count($spotifyCountriesList);
	return (object)array(
		'unrestricted' => $unrestricted,
		'territories' => $unrestricted ? array() : $terrs,
	);
}

function parseAlbum($n) {
	$artists = array();
	$ids = array();
	$album = array(
		'uri' => $n->getAttribute('href'),
		'name' => null,
		'artists' => array(),
		'ids' => array(),
		'popularity' => 0.0,
		'isVariousArtists' => false,
		'availability' => (object)array(
			'unrestricted' => false,
			'territories' => array()),
	);

	$nl = $n->childNodes;

	for ($i=0;$i<$nl->length;$i++) {
		$n2 = $nl->item($i);
		if ($n2->nodeType === XML_ELEMENT_NODE) {
			switch ($n2->nodeName) {
			case 'name':
				$album['name'] = $n2->nodeValue;
				break;

			case 'artist':
				$artist = parseArtist($n2);
				$artists[] = $artist;
				if ($album['isVariousArtists'] === false)
					$album['isVariousArtists'] = ($artist->name === 'Various Artists' ? true : false);
				break;

			case 'id':
				$ids[] = parseId($n2);
				break;

			case 'popularity':
				$album['popularity'] = floatval($n2->nodeValue);
				break;

			case 'availability':
				$album['availability'] = parseAvailability($n2);
				break;
			}
		}
	}

	$album['artists'] = $artists;
	$album['ids'] = $ids;
	return (object)$album;
}

$totalPages = 1;

// Go get all the pages in the xml feed
// Note: $totalPages is calculated after the first page is fetched and processed inside the for statement
for ($pageNo = 1; $pageNo <= $totalPages; $pageNo++) {
	$startIndex = $itemsPerPage = 0; # reset

	$opts = array(
	    'http' => array(
	        'user_agent' => 'PHP libxml agent',
	    )
	);
	$context = stream_context_create($opts);
	libxml_set_streams_context($context);

	$dom = new DOMDocument();

	$xml = new stdClass();
	$xml->preserveWhiteSpace = false;

	if($dom->load('http://ws.spotify.com/search/1/album?q=tag:new&page='.$pageNo, LIBXML_DTDLOAD) === false) {
		error_log( 'Could not load page: '.$pageNo);
		continue;
	}

	$doc = $dom->documentElement;

	$nl = $doc->childNodes;
	for ($i=0;$i<$nl->length;$i++) {
		$n = $nl->item($i);
		if ($n->nodeType === XML_ELEMENT_NODE) {
			switch ($n->nodeName) {
			case 'opensearch:totalResults':
				$totalResults = max(intval($n->nodeValue), $totalResults);
				if ($itemsPerPage !== 0)
					$totalPages = ceil($totalResults/$itemsPerPage);
				break;

			case 'opensearch:startIndex':
				$startIndex = intval($n->nodeValue);
				break;

			case 'opensearch:itemsPerPage':
				$itemsPerPage = intval($n->nodeValue);
				if ($totalResults !== 0)
					$totalPages = ceil($totalResults/$itemsPerPage);
				break;

			case 'album':
				$album = parseAlbum($n);
				$album_uri = xe($album->uri);
				$available_date = xe($date);

				$artists = array();

				$database->beginTransaction();
				foreach ($album->artists as $artist) {
					$parse_artist_uri = explode(":", $artist->uri);
					$artists[] = $parse_artist_uri[2];
					$query = $database->prepare('INSERT IGNORE INTO artists (id, name) VALUES (:id, :name)');
					$query->bindParam(':id', $parse_artist_uri[2], PDO::PARAM_STR);
					$query->bindParam(':name', $artist->name, PDO::PARAM_STR);
					$query->execute();
				}
				$database->commit();

				$database->beginTransaction();

				$query = $database->prepare('INSERT IGNORE INTO albums (id, upc, name, release_date, availability, popularity, tracks, image, artists, genres) VALUES (:id, :upc, :name, NOW(), :availability, :popularity, :tracks, :image, :artists, :genres)');

				$parse_album_uri = explode(":", $album_uri);
				$query->bindParam(':id', $parse_album_uri[2], PDO::PARAM_STR);
				$query->bindParam(':upc', $album->ids[0]->id, PDO::PARAM_STR);
				$query->bindParam(':name', $album->name, PDO::PARAM_STR);

				$availability = $album->availability->unrestricted ? 'ANY' : xe(implode(' ', $album->availability->territories));
				$query->bindParam(':availability', $availability, PDO::PARAM_STR);

				$null = NULL;
				$query->bindParam(':tracks', $null, PDO::PARAM_NULL);
				$query->bindParam(':popularity', $album->popularity, PDO::PARAM_STR);

				$query->bindParam(':image', $null, PDO::PARAM_NULL);

				$s_artists = serialize( $artists );
				$query->bindParam(':artists', $s_artists, PDO::PARAM_STR);
				$query->bindParam(':genres', $null, PDO::PARAM_NULL);

				$query->execute();
				$database->commit();

/*
// Why?
				$query = $database->prepare('SELECT * FROM spotify_releases WHERE album_uri = :album_uri AND available = :available');
				$query->bindParam(':album_uri', $album_uri, PDO::PARAM_STR);
				$query->bindParam(':available', $available_date, PDO::PARAM_STR);
				$query->execute();

				if ($query->rowCount() && ($album->availability->unrestricted || (count($album->availability->territories) && (!isset($region) || strrpos(strtolower(xe(implode(' ', $album->availability->territories))), $region) !== false)))) {
					# separate VA so we can put the at bottom later
					if ($album->isVariousArtists)
						$vaAlbums[] = $album;
					else
						$albums[] = $album;
				}
*/
				break;
			}
		}
	}
}
/* Now we have...
$totalPages = all feed pages available. Only used above
$startIndex = count of item # offset. Not used.
$albums (array) = Processed album info. Used below.
Everything is pushed to the db
*/

?>
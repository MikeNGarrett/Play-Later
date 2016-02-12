<?php
echo '-------------------';
echo date('Y-m-d').PHP_EOL;
/*

Cron is intended to process the xml feed of new releases from Spotify and get the data into the database.
This should take care of all the heavy lifting and scheduled processing, so index.php only has to work on display.

*/
$debug = false;
error_reporting(E_ALL);

include_once '../config.php';
$database = new Database();

include_once '../spotify-config.php';
$spotify = new Spotify();

$baseDir = dirname(realpath('./'));
$spotifyCountries = loadCountryCodesMap($baseDir.'/ISO-3166-1-alpha-2-country-codes-spotify.tsv');

function spotifyCountriesFilter($v) {
	global $spotifyCountries;
	return isset($spotifyCountries[strtoupper($v)]);
}

$date = date('Y-m-d');

$cachePath = check_dir( $baseDir.'/cache/' );
if ( !$cachePath ) {
	echo "Could not create cache directory".PHP_EOL;
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

$allCountries = loadCountryCodesMap($baseDir.'/ISO-3166-1-alpha-2-country-codes.tsv');
$spotifyCountriesList = array_keys($spotifyCountries);

/*
function countryCodeToName($xx) {
	global $allCountries;
	return isset($allCountries[$xx]) ? $allCountries[$xx] : $xx;
}
*/

$totalPages = 1;
$i = 0;

// Go get all the pages in the xml feed
// Note: $totalPages is calculated after the first page is fetched and processed inside the for statement
for ($pageNo = 1; $pageNo <= $totalPages; $pageNo++) {
echo 'Page: '.$pageNo.' of '.$totalPages.PHP_EOL;
	$database->beginTransaction();
	$startIndex = $itemsPerPage = 0; # reset

	$get = array('q' => 'tag:new', 'type' => 'album', 'limit' => 50, 'offset' => $pageNo*50);
	$url = 'https://api.spotify.com/v1/search';
	$options = array();
	$defaults = array(
	    CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get),
	    CURLOPT_HEADER => 0,
	    CURLOPT_RETURNTRANSFER => TRUE,
	    CURLOPT_TIMEOUT => 4
	);

    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if( ! $result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
    }
    curl_close($ch);
    $jsonresult = json_decode( $result );

	$totalPages = floor( $jsonresult->albums->total / $jsonresult->albums->limit );

	$items = $jsonresult->albums->items;

	foreach( $items as $album ) {
		$query = $database->prepare('INSERT IGNORE INTO albums (id, name, release_date, availability, popularity, tracks, image, artists, genres, type) VALUES (:id, :name, :release_date, :availability, :popularity, :tracks, :image, :artists, :genres, :type)');

		if( $debug ) { echo 'id: '.$album->id.PHP_EOL;  }
		$query->bindParam(':id', $album->id, PDO::PARAM_STR);
		if( $debug ) { echo 'name: '.$album->name.PHP_EOL;  }
		$query->bindParam(':name', $album->name, PDO::PARAM_STR);

		$release_date = date('Y-m-d');
		if( $debug ) { echo 'release date: '.$release_date.PHP_EOL;  }
		$query->bindParam(':release_date', $release_date, PDO::PARAM_STR);

		$availability = $album->available_markets ? 'ANY' : implode(' ', $album->available_markets);
		if( $debug ) { echo 'availability: '.$availability.PHP_EOL;  }
		$query->bindParam(':availability', $availability, PDO::PARAM_STR);

		$null = NULL;
		$zero = 0;
		if( $debug ) { echo 'pop: '.$zero.PHP_EOL;  }
		$query->bindParam(':popularity', $zero, PDO::PARAM_STR);
		if( $debug ) { echo 'tracks: '.$null.PHP_EOL;  }
		$query->bindParam(':tracks', $null, PDO::PARAM_NULL);

		// Assume this is the big image.
		$image = $null;
		if( isset( $album->images[0] ) ) {
			$image = $album->images[0]->url;
		}
		if( $debug ) { echo 'image: '.$image.PHP_EOL;  }
		$query->bindParam(':image', $image, PDO::PARAM_STR);

		if( $debug ) { echo 'artists: '.$null.PHP_EOL;  }
		$query->bindParam(':artists', $null, PDO::PARAM_NULL);
		if( $debug ) { echo 'genres: '.$null.PHP_EOL;  }
		$query->bindParam(':genres', $null, PDO::PARAM_NULL);

		if( $debug ) { echo 'type: '.$album->album_type.PHP_EOL;  }
		$query->bindParam(':type', $album->album_type, PDO::PARAM_STR);

		$test = $query->execute();
		if( $test ) {
			$i++;
		}
	}
	try {
		$database->commit();
	}
	catch (PDOException $e) {
	    echo 'Mysql connection error: '.$e->getMessage().PHP_EOL;
	}
}
echo 'Processed '.$i.' new albums'.PHP_EOL;

$database->beginTransaction();
$query2 = $database->prepare('SELECT id FROM albums WHERE artists IS NULL');
$query2->execute();
$album_ids = array();
$i = 0;
while ($row = $query2->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
	$album_ids[] = $row[0];
 	if( count($album_ids) >= 20) {
		$albums = $spotify->api->getAlbums($album_ids);
		foreach( $albums->albums as $album ) {
			if( isset( $album->artists ) && count( $album->artists) > 0 ) {
				$artist_query = $database->prepare('UPDATE albums SET artists=:artists WHERE id=:id');
				$release_artists = serialize( $album->artists );
				if( count( $release_artists ) >= 1024 ) {
					print 'Aritst content too long'.PHP_EOL;
				}
				$artist_query->bindParam(':artists', $release_artists, PDO::PARAM_LOB);
				$artist_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$test = $artist_query->execute();
				if( $test ) {
					$i++;
				}
			}
		}
		$album_ids = array();
	}
	if( $debug ) { echo $i.PHP_EOL;  }
}
echo 'Found '.$i.' artists'.PHP_EOL;
try {
	$database->commit();
}
catch (PDOException $e) {
    echo 'Mysql connection error: '.$e->getMessage().PHP_EOL;
}
?>
<?php
define('APPPATH', dirname(__FILE__).'/');

echo '-------------------';
echo date('Y-m-d').PHP_EOL;

include_once '../config.php';
$database = new Database();

require_once dirname(__FILE__).'/../last-fm/lastfm.api.php';

// Last.fm keys
$api_key = '';
$api_secret = '';
$logged_in = false;

LastFM_Caller_CallerFactory::getDefaultCaller()->setApiKey($api_key);
LastFM_Caller_CallerFactory::getDefaultCaller()->setApiSecret($api_secret);

//Installing cache (optional)
LastFM_Caller_CallerFactory::getDefaultCaller()->setCache(new LastFM_Cache_DiskCache(dirname(__FILE__).'/last-fm-cron'));

//Using rate limiter (optional)
LastFM_Caller_CallerFactory::getDefaultCaller()->setRateLimit(300);

$time_three_fridays_ago = strtotime('last friday') - ( 21 * 24 * 60 * 60 );
$three_fridays_ago = date( 'Y-m-d', $time_three_fridays_ago );

// Temporary for getting everything in the db
$query = $database->prepare('SELECT artists, name, popularity, id, genres FROM albums WHERE (artists IS NOT NULL) AND (mbid IS NULL) ORDER BY popularity DESC'); //(release_date>=:release_date)
//$query->bindParam(':release_date',  $three_fridays_ago, PDO::PARAM_STR);
$query->execute();
$i = 0;

$database->beginTransaction();
while ($row = $query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
	// We don't have an artist, so continue
	if( !is_serialized( $row[0] ) )
		continue;

	// artists
	$raw_artists = unserialize( $row[0] );
	// we only need one
	$artist_name = $raw_artists[0]->name;
	// album name
	$album_name = $row[1];
	// popularity
	$popularity = $row[2];
	// album id
	$id = $row[3];
	if( !isset( $id ) || empty( $id ) )
		continue;

	// genres
	$genres = false;
	if( isset( $row[4] ) && !empty( $row[4] ) && is_serialized( $row[4] ) ) {
		$genres = unserialize( $row[4] );
	}

	$fail = 0;
	try {
		$album_xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('album.getInfo', array(
			'artist' => $artist_name,
			'album'  => $album_name,
			'mbid'   => null,
			'lang'   => null,
			'autocorrect' => 1
		));
	} catch(LastFM_Error $e) {
		$fail = 1;
		if( $e->getCode() == 29 ) {
			echo 'Processed '.$i.PHP_EOL;
			echo 'Rate limit exceeded. Stopping requests.'.PHP_EOL;
			break;
		}
		echo 'Cannot find album: '.$album_name.' by '.$artist_name.PHP_EOL;

		$mbid1_query = $database->prepare('UPDATE albums SET mbid=:mbid WHERE id=:id');
		$mbid1 = "-";
		$mbid1_query->bindParam(':mbid', $mbid1, PDO::PARAM_STR);
		$mbid1_query->bindParam(':id', $id, PDO::PARAM_STR);
		$mbid1_query->execute();

	}
	if( !$fail ) {
		// The original query requires this to be present. Otherwise, popularity will get out of hand.
		if( !isset( $album_xml->mbid ) || empty( $album_xml->mbid ) ) {
			$mbid_query = $database->prepare('UPDATE albums SET mbid=:mbid WHERE id=:id');
			$mbid = "-";
			$mbid_query->bindParam(':mbid', $mbid, PDO::PARAM_STR);
			$mbid_query->bindParam(':id', $id, PDO::PARAM_STR);
			$mbid_query->execute();

			continue;
		}

		$mbid = ( $album_xml->mbid && trim( $album_xml->mbid ) ) ? strval( $album_xml->mbid ) : '';
		$mbid_query = $database->prepare('UPDATE albums SET mbid=:mbid WHERE id=:id');
		$mbid_query->bindParam(':mbid', $mbid, PDO::PARAM_STR);
		$mbid_query->bindParam(':id', $id, PDO::PARAM_STR);
		$mbid_query->execute();

		if( isset( $album_xml->playcount ) && $album_xml->playcount > 0 && isset( $album_xml->listeners ) && $album_xml->listeners > 0 ) {
			$new_pop = $popularity + round( $album_xml->playcount / $album_xml->listeners );
			$popularity_query = $database->prepare('UPDATE albums SET popularity=:popularity WHERE id=:id');
			$popularity_query->bindParam(':popularity', $new_pop, PDO::PARAM_STR);
			$popularity_query->bindParam(':id', $id, PDO::PARAM_STR);
			$popularity_query->execute();
		}

		if( isset( $album_xml->tags->tag ) && !empty( $album_xml->tags->tag ) && count( $album_xml->tags->tag ) > 0 ) {
			$last_tags = array();
			foreach( $album_xml->tags->tag as $last_tag ) {
				$last_tags[] = ( $last_tag->name && trim( $last_tag->name ) ) ? strval( $last_tag->name ) : '';

				$all_genre_query = $database->prepare('INSERT IGNORE INTO genres SET name=:name');
				$all_genre_query->bindParam(':name', $last_tag, PDO::PARAM_STR);
				$all_genre_query->execute();

			}
			if( $genres ) {
				if( !is_array( $genres ) ) {
					$genres = array();
				}
				if( !is_array( $last_tags ) ) {
					$last_tags = array();
				}

				$genres = array_merge( $genres, $last_tags );
				$s_genres = serialize( $genres );

				if( preg_match('/a:1:{i:0;a:/', $s_genres) > 0) {
					echo 'Genre array problem '.print_r($s_genres, true);
				} else {
					$genre_query = $database->prepare('UPDATE albums SET genres=:genres WHERE id=:id');
					$genre_query->bindParam(':genres', $s_genres, PDO::PARAM_LOB);
					$genre_query->bindParam(':id', $id, PDO::PARAM_STR);
					$genre_query->execute();
				}
			}
		}
		$i++;
	}
}
echo 'Updated '.$i.' albums from last.fm'.PHP_EOL;
try {
	$database->commit();
}
catch (PDOException $e) {
    echo 'Mysql connection error: '.$e->getMessage().PHP_EOL;
}
?>
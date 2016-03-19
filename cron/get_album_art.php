<?php
$debug = false;
error_reporting(E_ALL);

echo '-------------------';
echo date('Y-m-d').PHP_EOL;

include_once '../spotify-config.php';
$spotify = new Spotify();

include_once '../config.php';
$database = new Database();

require_once("../phpfastcache.php");
$cache = phpFastCache();

$query = $database->prepare('SELECT id FROM albums WHERE (image IS NULL) OR (tracks IS NULL) OR (genres IS NULL) OR (artists IS NULL) OR (type IS NULL)');
$query->execute();
$album_ids = array();
$artist_ids = array();
$a = 0;

while ($row = $query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
	if( isset( $row[0] ) && !empty( $row[0] ) ) {
		$album_ids[] = $row[0];
	}
/*
	if( $row[1] && is_serializes( $row[1] ) ) {
		$all_artists = unserialize( $row[1] );
		foreach( $all_artists as $new_artist ) {
			if( isset( $artist_ids[$row[0]] ) && is_array( $artist_ids[$row[0]] ) ) {
				$artist_ids[$row[0]] = array_merge( $artist_ids[$row[0]], array($new_artist->id) );
			} else {
				$artist_ids[$row[0]] = array( $new_artist->id );
			}
		}
	} else {
		$artist_ids[$row[0]] = null;
	}
continue;
*/
	if( count($album_ids) >= 20) {
		$albums = $spotify->api->getAlbums($album_ids);

		$artist_ids = array();
		$album_artist_count = array();

		$database->beginTransaction();
		if( $debug ) { echo "Found ".count($albums->albums)." albums".PHP_EOL; }
		foreach( $albums->albums as $album ) {
			if( $debug ) { echo 'Album: '.$album->name.PHP_EOL; }
			$a++;

			$album_artist_count[$album->id] = 1;
			foreach( $album->artists as $artist ) {
				$artist_ids[] = $artist->id;
				$album_artist_count[$album->id]++;
			}

			if( $debug ) { echo 'Artists: '.print_r( $album->artists, true ).PHP_EOL; }

			$artist_query = $database->prepare('UPDATE albums SET artists=:artists WHERE id=:id');
			$release_artists = serialize( $album->artists );
			$artist_query->bindParam(':artists', $release_artists, PDO::PARAM_LOB);
			$artist_query->bindParam(':id', $album->id, PDO::PARAM_STR);
			$artist_query->execute();

			if( isset( $album->album_type ) ) {
				$type_query = $database->prepare('UPDATE albums SET type=:type WHERE id=:id');
				$type_query->bindParam(':type', $album->album_type, PDO::PARAM_LOB);
				$type_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$type_query->execute();
			}
			if( $album->release_date_precision == "day" ) {
				if( $debug ) { echo 'Release Date: '.$album->release_date.PHP_EOL; }
				$release_day = $album->release_date;
				$release_query = $database->prepare('UPDATE albums SET release_date=:release_day WHERE id=:id');
				$release_query->bindParam(':release_day', $release_day, PDO::PARAM_LOB);
				$release_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$release_query->execute();
			}
			if( isset( $album->images ) && count( $album->images ) > 0 ) {
				if( $debug ) { echo 'Images: '.print_r( $album->images[0], true ).PHP_EOL; }
				$image_url = $album->images[0]->url;
				$image_query = $database->prepare('UPDATE albums SET image=:image WHERE id=:id');
				$image_query->bindParam(':image', $image_url, PDO::PARAM_STR);
				$image_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$image_query->execute();
			}
			if( isset( $album->tracks->items ) && count( $album->tracks->items ) > 0 ) {
				if( $debug ) { echo 'Tracks: '.count( $album->tracks->items ).PHP_EOL; }
				$track_count = count( $album->tracks->items );
				$tracks_query = $database->prepare('UPDATE albums SET tracks=:tracks WHERE id=:id');
				$tracks_query->bindParam(':tracks', $track_count, PDO::PARAM_INT);
				$tracks_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$tracks_query->execute();
			}
			// Genres aren't filled in at all on albums. Ignore.
/*
			if( isset( $album->genres ) && count( $album->genres ) > 0 ) {
				if( $s_artists_genres && is_serialized( $s_artists_genres ) ) {
					$artists_genres = unserialize( $s_artists_genres );
					$artists_genres = array_merge( $artists_genres, $album->genres );
				} else {
					$artists_genres = $album->genres;
				}
				$genres = serialize( $album->genres );
				$tracks_query = $database->prepare('UPDATE albums SET genres=:genres WHERE id=:id');
				$tracks_query->bindParam(':genres', $genres, PDO::PARAM_LOB);
				$tracks_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$tracks_query->execute();
			}
*/
			$s_artist = '';
		}
		if( $debug ) { echo 'Done with Albums'.PHP_EOL; }
		$artists = $spotify->api->getArtists($artist_ids);

		foreach( $album_artist_count as $album_id => $count ) {
			$artists_genres = '';
			for($i=0;$i<$count;$i++) {
				$artist = array_shift( $artists->artists );

				$artist2_query = $database->prepare('INSERT IGNORE INTO artists (id, name) VALUES (:id, :name)');
				$artist2_query->bindParam(':id', $artist->id, PDO::PARAM_STR);
				$artist2_query->bindParam(':name', $artist->name, PDO::PARAM_STR);
				$artist2_query->execute();

				if( isset( $artist->genres ) && count( $artist->genres ) > 0 ) {
					foreach( $artist->genres as $g ) {
						$all_genre_query = $database->prepare('INSERT IGNORE INTO genres SET name=:name');
						$all_genre_query->bindParam(':name', $g, PDO::PARAM_STR);
						$all_genre_query->execute();
					}

					if( $artists_genres && is_array( $artists_genres ) ) {
						$artists_genres = array_merge( $artists_genres, $artist->genres );
					} else {
						$artists_genres = $artist->genres;
					}

					$s_artists_genres = serialize( $artists_genres );
					$genre_query = $database->prepare('UPDATE albums SET genres=:genres WHERE id=:id');
					$genre_query->bindParam(':genres', $s_artists_genres, PDO::PARAM_LOB);
					$genre_query->bindParam(':id', $album->id, PDO::PARAM_STR);
					$genre_query->execute();
				}
			}
		}
		echo "Processed ".$a." albums".PHP_EOL;
		try {
			$database->commit();
        }
        catch (PDOException $e) {
            error_log( 'Mysql connection error: '.$e->getMessage() );
        }

		$album_ids = array();
		$artist_ids = array();
	}

}
echo 'Updated '.$a.' albums and artists'.PHP_EOL;

$genre_query = $database->prepare("SELECT name FROM genres");
$genre_query->execute();

$select_genres = array();
$a = 0;
while ($row = $genre_query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
	$genre = $row[0];
	$url_genres = $genre;
	$selected = '';
	$count_genre_query = $database->prepare("SELECT COUNT(*) FROM albums WHERE ( genres LIKE :genre ) AND type='album'");
	// A way to check for an exact match in a serialized array
	$p_genre = '%"'.$genre.'"%';
	$count_genre_query->bindParam(':genre', $p_genre, PDO::PARAM_STR);
	$count_genre_query->execute();

	$genre_count = $count_genre_query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT);
	$genre_count = $genre_count[0];
	if( $genre_count > 0 ) {
		$select_genres[$genre] = '<option value="'.$url_genres.'"'.$selected.'>'.$genre.' ('.$genre_count.')</option>';
	}
}
asort( $select_genres );
$cache->delete("genres");
$cache->set("genres", $select_genres, 60*60*24*365); // Cache for 365 days... basically indefinitely
echo 'genre cache updated'.PHP_EOL;
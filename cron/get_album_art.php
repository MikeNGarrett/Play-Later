<?php
//TODO: get rid of all these stupid requires
require '../spotify/SpotifyWebAPI.php';
require '../spotify/SpotifyWebAPIException.php';
require '../spotify/Session.php';
require '../spotify/Request.php';

$session = new SpotifyWebAPI\Session('CLIENT_ID', 'CLIENT_SECRET', 'REDIRECT_URI');

$api = new SpotifyWebAPI\SpotifyWebAPI();

error_reporting(E_ALL);

include_once '../config.php';
$database = new Database();

$query = $database->prepare('SELECT id, artists FROM albums WHERE (image IS NULL) OR (tracks IS NULL) OR (genres IS NULL)');
$query->execute();
$album_ids = array();
while ($row = $query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
	$album_ids[] = $row[0];
	$all_artists = unserialize( $row[1] );
	foreach( $all_artists as $new_artist ) {
		$artist_ids[$row[0]] = $new_artist;
	}
	if( count($album_ids) >= 20) {
		$albums = $api->getAlbums($album_ids);

		$artists = $api->getArtists($artist_ids);

		$database->beginTransaction();
		foreach( $albums->albums as $album ) {
			$artist = array_shift($artists->artists);
			if( isset( $artist->genres ) && count( $artist->genres ) > 0 ) {
				foreach( $artist->genres as $g ) {
					$all_genre_query = $database->prepare('INSERT IGNORE INTO genres SET name=:name');
					$all_genre_query->bindParam(':name', $g, PDO::PARAM_STR);
					$all_genre_query->execute();
				}
				$s_artist = serialize( $artist->genres );
				$genre_query = $database->prepare('UPDATE albums SET genres=:genres WHERE id=:id');
				$genre_query->bindParam(':genres', $s_artist, PDO::PARAM_LOB);
				$genre_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$genre_query->execute();
			}
			if( $album->release_date_precision == "day" ) {
				$release_day = $album->release_date;
				$release_query = $database->prepare('UPDATE albums SET release_date=:release_day WHERE id=:id');
				$release_query->bindParam(':release_day', $release_day, PDO::PARAM_LOB);
				$release_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$release_query->execute();
			}
			if( isset( $album->images ) && count( $album->images ) > 0 ) {
				$image_url = $album->images[0]->url;
				$image_query = $database->prepare('UPDATE albums SET image=:image WHERE id=:id');
				$image_query->bindParam(':image', $image_url, PDO::PARAM_STR);
				$image_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$image_query->execute();
			}
			if( isset( $album->tracks->items ) && count( $album->tracks->items ) > 0 ) {
				$track_count = count( $album->tracks->items );
				$tracks_query = $database->prepare('UPDATE albums SET tracks=:tracks WHERE id=:id');
				$tracks_query->bindParam(':tracks', $track_count, PDO::PARAM_INT);
				$tracks_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$tracks_query->execute();
			}
			// Genres aren't filled in at all.
			if( isset( $album->genres ) && count( $album->genres ) > 0 ) {
				$genres = serialize( $album->genres );
				$tracks_query = $database->prepare('UPDATE albums SET genres=:genres WHERE id=:id');
				$tracks_query->bindParam(':genres', $genres, PDO::PARAM_LOB);
				$tracks_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$tracks_query->execute();
			}
		}
		$database->commit();

		$album_ids = array();
		$artist_ids = array();
	}
}

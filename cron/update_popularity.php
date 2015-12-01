<?php
error_reporting(E_ALL);

include_once '../spotify-config.php';
$spotify = new Spotify();

include_once '../config.php';
$database = new Database();

$time_three_fridays_ago = strtotime('last friday') - ( 21 * 24 * 60 * 60 );
$three_fridays_ago = date( 'Y-m-d', $time_three_fridays_ago );
$query = $database->prepare('SELECT id FROM albums WHERE release_date>=:release_date');
$query->bindParam(':release_date',  $three_fridays_ago, PDO::PARAM_STR);
$query->execute();

$album_ids = array();

while ($row = $query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
	$album_ids[] = $row[0];
	if( count($album_ids) >= 20) {
		$albums = $spotify->api->getAlbums($album_ids);

		$database->beginTransaction();
		foreach( $albums->albums as $album ) {
			if( isset( $album->popularity ) && $album->popularity > 0 ) {
				$popularity_query = $database->prepare('UPDATE albums SET popularity=:popularity WHERE id=:id');
				$popularity_query->bindParam(':popularity', $album->popularity, PDO::PARAM_STR);
				$popularity_query->bindParam(':id', $album->id, PDO::PARAM_STR);
				$popularity_query->execute();

			}
		}
		$database->commit();

		$album_ids = array();
		$albums = '';
	}

}
<?php
// $id is passed from the router
if( !isset( $id ) || empty( $id ) ) {
	// Legacy functionality still needs to be supported
	if( isset( $_GET['album'] ) && !empty( $_GET['album'] ) ) {
		$id = $_GET['album'];
	} else {
		error_log( 'Problem adding tracks. No album passed: '.print_r( get_defined_vars(), true ).PHP_EOL );
		echo "No track specified";
		die;
	}
}
session_start();

include_once dirname(__FILE__).'/../spotify-config.php';
$spotify = new Spotify();

if( empty( $_SESSION['spotify_token'] ) ) {
	echo 'Cannot retrieve token';
	die;
}

//$album_id = $_GET['album'];
$playlist = $_SESSION['playlist'];

$spotify->api->setAccessToken( $_SESSION['spotify_token'] );
$me = $spotify->api->me();

$album = $spotify->api->getAlbum( $id );

$tracks = $album->tracks;

$all_tracks = array();
foreach( $tracks->items as $track ) {
	$all_tracks[] = $track->id;
}
$spotify->api->addUserPlaylistTracks( $me->id, $playlist->id, $all_tracks );
echo 'Added Album to "Play Later" Playlist';
die;

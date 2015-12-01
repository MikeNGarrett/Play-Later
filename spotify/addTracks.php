<?php
session_start();

include_once '../spotify-config.php';
$spotify = new Spotify();

if( empty( $_SESSION['spotify_token'] ) ) {
	echo 'Cannot retrieve token';
	die;
}
if( empty( $_GET['album'] ) ) {
	echo "No track specified";
	die;
}

$album_id = $_GET['album'];
$playlist = $_SESSION['playlist'];

$spotify->api->setAccessToken( $_SESSION['spotify_token'] );
$me = $spotify->api->me();

$album = $spotify->api->getAlbum( $album_id );

$tracks = $album->tracks;

$all_tracks = array();
foreach( $tracks->items as $track ) {
	$all_tracks[] = $track->id;
}
$spotify->api->addUserPlaylistTracks( $me->id, $playlist->id, $all_tracks );
echo 'Added Album to "Play Later" Playlist';
die;

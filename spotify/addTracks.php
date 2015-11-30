<?php
session_start();

require 'SpotifyWebAPI.php';
require 'SpotifyWebAPIException.php';
require 'Session.php';
require 'Request.php';

if( empty( $_SESSION['spotify_token'] ) ) {
	echo 'Cannot retrieve token';
	die;
}
if( empty( $_GET['album'] ) ) {
	echo "No track specified";
}

$album_id = $_GET['album'];
$playlist = $_SESSION['playlist'];

$api = new SpotifyWebAPI\SpotifyWebAPI();
$api->setAccessToken( $_SESSION['spotify_token'] );
$me = $api->me();

$album = $api->getAlbum( $album_id );

$tracks = $album->tracks;

$all_tracks = array();
foreach( $tracks->items as $track ) {
	$all_tracks[] = $track->id;
}
$api->addUserPlaylistTracks( $me->id, $playlist->id, $all_tracks );
echo 'Added Album to "Play Later" Playlist';
die;

<?php
include_once '../spotify-config.php';
$spotify = new Spotify();

if (isset($_GET['code'])) {
session_start();
    $spotify->session->requestAccessToken($_GET['code']);
    $access_token = $spotify->session->getAccessToken();
	$refresh_token = $spotify->session->getRefreshToken();
	$expires = $spotify->session->getTokenExpiration();
	$_SESSION['spotify_token'] = $access_token;
	$_SESSION['spotify_refresh_token'] = $refresh_token;
	$_SESSION['spotify_expires'] = $expires;
    $spotify->api->setAccessToken($access_token);

//    print_r($spotify->api->me());
header( 'Location: '.$spotify->callback_url );

} else {
    $scopes = array(
        'scope' => array(
	        'playlist-read-private',
	        'playlist-read-collaborative',
	        'playlist-modify-private',
	        'playlist-modify-public',
        ),
    );

    header('Location: ' . $spotify->session->getAuthorizeUrl($scopes));
}

<?php
require 'SpotifyWebAPI.php';
require 'SpotifyWebAPIException.php';
require 'Session.php';
require 'Request.php';

$domain = ''; // EG: 'http://redgarrett.com';

$session = new SpotifyWebAPI\Session('CLIENT_ID', 'CLIENT_SECRET', 'REDIRECT_URI');

$api = new SpotifyWebAPI\SpotifyWebAPI();

if (isset($_GET['code'])) {
session_start();
    $session->requestAccessToken($_GET['code']);
    $access_token = $session->getAccessToken();
	$refresh_token = $session->getRefreshToken();
	$expires = $session->getTokenExpiration();
	$_SESSION['spotify_token'] = $access_token;
	$_SESSION['spotify_refresh_token'] = $refresh_token;
	$_SESSION['spotify_expires'] = $expires;
    $api->setAccessToken($access_token);

//    print_r($api->me());
header( 'Location: '.$domain.'/lab/play-later/' );

} else {
    $scopes = array(
        'scope' => array(
	        'playlist-read-private',
	        'playlist-read-collaborative',
	        'playlist-modify-private',
	        'playlist-modify-public',
        ),
    );

    header('Location: ' . $session->getAuthorizeUrl($scopes));
}

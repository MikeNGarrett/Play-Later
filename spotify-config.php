<?php
function __autoload( $class_name ) {
	$class = str_replace( '\\', '/', $class_name );
	if (file_exists(dirname(__FILE__).'/'.$class . '.php')) {
		require_once dirname(__FILE__).'/'.$class . '.php';
		return true;
	}
	return false;
}

class Spotify
{
	public $callback_url;
	public $session;
	public $api;

	{
		// Spotify credentials
		$spotify_client_id = 'CLIENT_ID';
		$spotify_client_secret = 'CLIENT_SECRET';
		// eg http://redgarrett.com/lab/play-later/spotify/ or http://redgarrett.dev:8888/lab/play-later/spotify/ (MAMP example)
		$spotify_redirect_uri = 'REDIRECT_URI';
		// eg http://redgarrett.com/lab/play-later/ or http://redgarrett.dev:8888/lab/play-later/ (MAMP example)
		$this->callback_url = 'CALLBACK_URI';

		// Set up Spotify session and API
		$this->session = new SpotifyWebAPI\Session( $spotify_client_id, $spotify_client_secret, $spotify_redirect_uri );
		$this->api = new SpotifyWebAPI\SpotifyWebAPI();
	}

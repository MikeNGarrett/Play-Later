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

	// It's good to set default values for Spotify here.
	public function __construct( $spotify_client_id = '', $spotify_client_secret = '', $spotify_redirect_uri = '', $callback_url = '')
	{
		// Spotify credentials
		$this->callback_url = $callback_url;

		// Set up Spotify session and API
		$this->session = new SpotifyWebAPI\Session( $spotify_client_id, $spotify_client_secret, $spotify_redirect_uri );
		$this->api = new SpotifyWebAPI\SpotifyWebAPI();
	}
}
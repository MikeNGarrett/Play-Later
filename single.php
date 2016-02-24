<?php
if( !defined('CHECK') )
	header( 'Location: '.APPURL );


error_reporting(E_ALL);
if( !isset( $id ) || empty( $id ) ) {
	echo '<h1>No album specified</h1>';
	die;
}
$back_url = APPURL;
/*
if( preg_match('//', $_SERVER["HTTP_REFERER"]) > 0 ) { // Something like this: /^http:\/\/(www\.)?play-later.com(.*)?$/
	$back_url = $_SERVER["HTTP_REFERER"];
}
*/


include_once dirname(__FILE__).'/config.php';
$database = new Database();

include_once dirname(__FILE__).'/phpfastcache.php';
$cache = phpFastCache();

if( $cache->isExisting( $id."_single_album" ) ) {
	$album = $cache->get( $id."_single_album" );
} else {
	$query = $database->prepare("SELECT * FROM albums WHERE id=:album_id");
	$query->bindParam(':album_id', $id, PDO::PARAM_STR);
	$query->execute();
	$albums = $query->fetchAll();

	$album = $albums[0];
	$cache->get( $id."_single_album", $album, 60*60*24 ); // Cache for a day
}
$l_artists = '';
$s_artists = '';
if( isset( $album['artists'] ) && is_serialized( $album['artists'] ) ) {
	$artists = unserialize( $album['artists'] );
	foreach( $artists as $artist ) {
		$l_artists .= '<a class="artist" href="'.$artist->uri.'">'.$artist->name.'</a>, ';
		$s_artists .= $artist->name.', ';
	}
	$l_artists = rtrim($l_artists, ', ');
	$s_artists = rtrim($s_artists, ', ');
}
$s_genres = '';
if( isset( $album['genres'] ) && is_serialized( $album['genres'] ) ) {
	$genres = unserialize( $album['genres'] );
	foreach( $genres as $genre ) {
		if( is_array( $genre ) ) {
			foreach( $genre as $g ) {
				$s_genres .= $g.', ';
			}
		} else {
			$s_genres .= $genre.', ';
		}
	}
	$s_genres = rtrim($s_genres, ', ');
}
if( empty( $album ) ) {
	$album['name'] = $s_artists = 'unknown';
}
?>
<!DOCTYPE HTML>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="title" content="Play Later New Release: <?php echo $album['name'].' by '.$s_artists; ?>">
    <meta name="description" content="Get the details of the new release: <?php echo $album['name'].' by '.$s_artists; ?>.">
    <link rel="canonical" href="<?php echo APPURL; ?>" />

    <meta property="og:url" content="<?php echo APPURL; ?>"/>
    <meta property="og:title" content="Play Later New Release: <?php echo $album['name'].' by '.$s_artists; ?>"/>
    <meta property="og:description" content="Get the details of the new release: <?php echo $album['name'].' by '.$s_artists; ?>."/>

    <title>Play Later New Release: <?php echo $album['name'].' by '.$s_artists; ?></title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans:400,700,600,600italic,800' rel='stylesheet' type='text/css'>
	<link type="text/css" href="/css/select2.min.css" rel="stylesheet" />
	<link type="text/css" href="/css/site.css" rel="stylesheet" />
  </head>

  <body class="single-album">
    <header>
	  <h1>New Album Release - <?php echo $album['name'].' by '.$s_artists; ?></h1>
    </header>
    <a href="<?php echo $back_url; ?>" class="button">&laquo; Back to Play Later</a>
    <section class="clearfix">
	  <?php if( !empty( $album ) ) { ?>
		  <?php if( !empty( $album['image'] ) ) { ?> <a class="album-cover" href="spotify:album:<?php echo $album['id']; ?>"><img class="single-album-cover" src="<?php echo $album['image']; ?>" alt="Album cover for <?php echo $album['name'].' by '.$s_artists; ?>"></a> <?php } ?>
		  <ul class="album-details">
			<li><strong>Artist(s):</strong> <?php echo $l_artists; ?></li>
			<li><strong>Album:</strong> <a class="name" href="spotify:album:<?php echo $album['id']; ?>"><?php echo $album['name']; ?></a></li>
			<?php if( !empty( $album['release_date'] ) ) { ?><li><strong>Release Date:</strong> <?php echo $album['release_date']; ?></li><?php } ?>
			<?php if( !empty( $s_genres ) ) { ?><li><strong>Genres:</strong> <?php echo $s_genres; ?></li><?php } ?>
			<?php if( !empty( $album['availability'] ) ) { ?><li><strong>Availability:</strong> <?php echo $album['availability']; ?></li><?php } ?>
			<?php if( !empty( $album['popularity'] ) ) { ?><li><strong>Popularity:</strong> <?php echo $album['popularity'].'%'; ?></li><?php } ?>
			<?php if( !empty( $album['tracks'] ) ) { ?><li><strong>Number of Tracks:</strong> <?php echo $album['tracks']; ?></li><?php } ?>
			<?php if( !empty( $album['type'] ) ) { ?><li><strong>Release Type:</strong> <?php echo $album['type']; ?></li><?php } ?>
		  </ul>
	  <?php } else { ?>
	  	  <h2>Unknown album</h2>
	  	  <p>Unsure how you got here? Go back to the <a href="<?php echo $back_url; ?>">full album list</a>.</p>
	  <?php } ?>
    </section>
    <footer>
      <h2>Built on the open <a href="https://developer.spotify.com/web-api/">Spotify metadata API</a> and the <a href="http://www.last.fm/api">Last.fm API</a></h2>
      <p>
	    DEBUG:
		<?php
		if( isset( $me ) ) {
			echo 'Logged in as <a href="'.$me->href.'">'.$me->display_name.'</a>, ';
		}
		if( isset( $all_user_playlists_albums ) ) {
			echo 'Ingested '.count( $all_user_playlists_albums ).' albums from Play Later, ';
		}
		if( isset( $_SESSION['spotify_expires'] ) ) {
			echo 'Token Expires: '.date('Y-m-d h:m:s', $_SESSION['spotify_expires']).', ';
		}

		?>
	  </p>
	  <p><strong>Fork and contribute: <a href="https://github.com/MikeNGarrett/Play-Later">Play Later on Github</a></strong></p>
      <p>
	This is a simple hack built on top of the open
	<a href="https://developer.spotify.com/technologies/web-api/">Spotify metadata API</a>.
	Based on the wonderful work on <a href="http://spotifyreleases.com/">SpotifyReleases.com</a>.
      </p>
      <p>
	      Other sources: <a href="http://everynoise.com/spotify_new_releases.html">EveryNoise New Releases</a>, <a href="http://swarm.fm/">Swarm.fm</a>, and <a href="http://pansentient.com/new-on-spotify/">Pansentient's New on Spotify</a>.
      </p>
      <p>
	      <a href="http://everynoise.com/engenremap.html">List of all Spotify genres</a>
      </p>
      <h2>Proudly built in Alexandria, VA by <a href="http://redgarrett.com">Mike N Garrett</a>.</h2>
    </footer>
  </body>
</html>
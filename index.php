<?php
#
# New releases on Spotify
#
# A quick Spotify metadata API hack which displays a list of recently added
# albums in Spotify.
#
# MIT license (basically do what you want as long as this license is reproduced):
#
# Copyright (c) 2010-2014 Rasmus Andersson, Markus Persson
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

//TODO: handle expired Spotify tokens
//TODO: handle errors (in general) esp with playlists
//TODO: You're hanging on to the playlist too long (check for deleted playlist)
//TODO: Get playlist check to work right.
//TODO: differentiate clean/dirty and other duplicates
//TODO: consolidate requires and stuff
error_reporting(E_ALL);
session_start();

require 'spotify/SpotifyWebAPI.php';
require 'spotify/SpotifyWebAPIException.php';
require 'spotify/Session.php';
require 'spotify/Request.php';

include_once 'config.php';
$database = new Database();

$path_to_play_later = ''; //EG: 'http://redgarrett.com/lab/play-later';

// Only start a session if we have an authorized account
$logged_in = false;

$session = new SpotifyWebAPI\Session('CLIENT_ID', 'CLIENT_SECRET', 'REDIRECT_URI');
$api = new SpotifyWebAPI\SpotifyWebAPI();

if( !empty( $_SESSION['spotify_token'] ) && !empty( $_SESSION['spotify_expires'] ) ) {
	if ( $_SESSION['spotify_expires'] > time() ) {
		$session->refreshAccessToken( $_SESSION['spotify_refresh_token'] );
		$_SESSION['spotify_token'] = $session->getAccessToken();

		$api->setAccessToken($_SESSION['spotify_token']);
	} else {
		$api->setAccessToken( $_SESSION['spotify_token'] );
	}
	$me = $api->me();
	if( !isset( $_SESSION['playlist'] ) ) {
		$play_later = get_playlist( $me, $api );
		if( !$play_later ) {
			$play_later = $api->createUserPlaylist( $me->id, array( 'name' => 'Play Later' ) );
		}
		$_SESSION['playlist'] = $play_later;
	}
	$play_later = $_SESSION['playlist'];
	$logged_in = true;

	$full_playlist = $api->getUserPlaylist( $me->id, $play_later->id, array( 'fields' => 'tracks.items(track(album(id)))' ) );
	$all_albums = array();
	foreach( $full_playlist->tracks->items as $album ) {
		$all_albums[] = $album->track->album->id;
	}
}

$baseDir = dirname(realpath(__FILE__));
/* $spotifyCountries = loadCountryCodesMap($baseDir.'/ISO-3166-1-alpha-2-country-codes-spotify.tsv'); */

/*
function spotifyCountriesFilter($v) {
	global $spotifyCountries;
	return isset($spotifyCountries[strtoupper($v)]);
}

if (isset($_GET['region']) && spotifyCountriesFilter($_GET['region']))
	$region = strtolower($_GET['region']);
*/

if( isset( $_GET['date'] ) && empty( $_GET['date'] ) ) {
	unset( $_GET['date'] );
}
if( isset( $_GET['genres'] ) && empty( $_GET['genres'] ) ) {
	unset( $_GET['genres'] );
}


function get_playlist( $me, $api, $offset = 0 ) {
	$all_playlists = $api->getUserPlaylists( $me->id, array( 'offset' => $offset ) );
	$total = $all_playlists->total;
	foreach( $all_playlists->items as $playlist ) {
		if( $playlist->name == 'Play Later' ) {
			return $playlist;
		}
	}
	if( $offset + 20 <= $total ) {
		get_playlist( $me, $api, $offset + 20 );
	} else {
		return false;
	}
}

function xe($s) {
	static $u = array('&', '"', '<', '>');
	static $e = array('&#38;', '&#34;', '&#60;', '&#62;');
	return str_replace($u, $e, $s);
}


$date = date('Y-m-d');

//genre=art+rock genre=boy+band date=Last+Week
//Future functionality: go back in time week by week

$last_friday = strtotime( "last Friday" );
$one_week = 7 * 24 * 60 * 60;
$one_day = 24 * 60 * 60;

$from_day = $last_friday;
$to_day = time();

if( isset( $_GET['date'] ) ) {
	switch($_GET['date']) {
		case 'this-week':
		default :
			$from_day = $last_friday;
			$to_day = time();
		break;
		case 'last-week':
			$from_day = $last_friday - $one_week;
			$to_day = $last_friday - $one_day;
		break;
		case 'two-weeks':
			$from_day = $last_friday - $one_week - $one_week;
			$to_day = $last_friday - $one_week - $one_day;
		break;
	}
}
$previous_date = date( "Y-m-d", $from_day );
$next_date = date( "Y-m-d", $to_day );

$limit = 100;
$list_offset = 0;
// Required for is_numeric, that bitch
if( isset( $_GET['offset'] ) ) {
	$temp_offset = $_GET['offset'];
	if( is_numeric( $temp_offset ) ) {
		if( $temp_offset >= 0 && $temp_offset < 25000 ) {
			$list_offset = (int) $temp_offset;
		}
	}
}
$query = $database->prepare("SELECT * FROM albums WHERE ( tracks > 3 AND tracks < 25 ) AND ( release_date BETWEEN :lastfriday AND :thisfriday ) AND ( availability LIKE '%US%' OR availability='ANY' ) ORDER BY popularity DESC LIMIT :offset, :limit");
$query->bindParam(':lastfriday', $previous_date, PDO::PARAM_STR);
$query->bindParam(':thisfriday', $next_date, PDO::PARAM_STR);
$query->bindParam(':offset', $list_offset, PDO::PARAM_INT);
$query->bindParam(':limit', $limit, PDO::PARAM_INT);
$query->execute();
$albums = $query->fetchAll();


// TODO: replace this with some sort of collab from the genres table
// How do you match genre table to albums table?
// How do you only display relevant genres here?
$select_genres = array();
foreach( $albums as $key => &$album ) {
	if( isset( $album['genres'] ) ) {
		$a_genres = unserialize( $album['genres'] );
		foreach( $a_genres as $s_genre ) {
			$url_genres = xe($s_genre);
			$selected = '';
			if( in_array( $s_genre, $_GET['genres'] ) ) {
				$selected = ' selected';
			}
			$select_genres[$s_genre] = '<option value="'.$url_genres.'"'.$selected.'>'.$s_genre.'</option>';
		}
	}
	$get_artists = unserialize( $album['artists'] );
	$album['artists'] = array();

	foreach( $get_artists as &$artist ) {
		$query = $database->prepare('SELECT name FROM artists WHERE id=:id');
		$query->bindParam(':id', $artist, PDO::PARAM_STR);
		$query->execute();
		$artist_names = $query->fetchAll();
		$album['artists'][$artist] = $artist_names[0];
	}
}
unset($album);
asort( $select_genres );
?>
<!DOCTYPE HTML>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>New releases on Spotify, Rdio style</title>
	<link type="text/css" href="css/site.css" rel="stylesheet" />
  </head>

  <body>
    <header>
      <h1>New releases on <a href="http://www.spotify.com/">Spotify</a></h1>
      <h2>Showing the most popular released albums, no singles, no compilations</h2>
      <h3>Currently (as of 11/20/15) showing <em>all</em> releases. Starting next week (11/27/15), you will only see albums released on Friday or later for the current week.</h3>
      <?php if( !$logged_in ) { ?>
	      <a href="spotify/" class="button">Log In</a>
      <?php } ?>
	  <p>The Play Later buttons will add the selected album to a new (or existing) playlist called &ldquo;Play Later&rdquo;</p>
	  <form action="index.php" method="get">
		<select name="genres[]" multiple="multiple" size="10">
			<option value="">-- Genre --</option>
			<?php echo implode("", $select_genres); ?>
		</select>
		<select name="date">
			<option value="">-- Date Range --</option>
			<option value="this-week"<?php if( $_GET['date'] == 'this-week' ) { echo ' selected'; } ?>>This Week</option>
			<option value="last-week"<?php if( $_GET['date'] == 'last-week' ) { echo ' selected'; } ?>>Last Week</option>
			<option value="two-weeks"<?php if( $_GET['date'] == 'two-weeks' ) { echo ' selected'; } ?>>Two Weeks Ago</option>
		</select>
		<button type="submit">Filter</button>
	  </form>
<?php
/*
      <p>
		<img width="600" height="225" src="days.php" alt="8 latest release days" />
		<img width="600" height="225" src="months.php" alt="4 latest release months" />
      </p>
*/
?>
    </header>
    <section>
      <ol>
		<?php foreach ($albums as $album): ?>
		  <?php
			if( isset($album['genres']) ) {
			    $album_genres = unserialize( $album['genres'] );
			}
		    if( isset( $_GET['genres'] ) && !empty( $_GET['genres'] ) ) {
			    $passed_genres = $_GET['genres'];
			    if( empty( $album['genres'] ) || empty( array_intersect( $passed_genres, $album_genres ) ) ) {
				    continue;
				}
			}
		  ?>
		  <li class="album <?php echo $album['availability']; ?>">
		  	<p><a class="name" href="spotify:album:<?php echo $album['id']; ?>"><img src="<?php echo $album['image']; ?>" alt="<?php echo $album['name']; ?>"/></a></p>
		    <?php /* <p class="no"><?php echo $no+1?></p> */ ?>
		    <?php if( $logged_in ) { ?>
		    	<?php if( !array_search( $album['id'], $all_albums ) ) { ?>
			    <a href="spotify/addTracks.php?album=<?php echo $album['id']; ?>" data-album="<?php echo $album['id']; ?>" class="button play-later" target="_blank">Play Later</a>
			    <?php } else { ?>
					<p class="status">Album already in playlist</p>
			    <?php } ?>
		    <?php } ?>
			<h3>
		      <a class="name" href="spotify:album:<?php echo $album['id']; ?>" title="Album Availability: <?php echo $album['availability']; ?>"><?php echo $album['name']; ?></a>
		    </h3>
		    <h4>
			<?php
				$buf=array();
				foreach ($album['artists'] as $id => $names) {
					$buf[] = '<a class="artist" href="spotify:artist:'.$id.'">'.$names['name'].'</a>';
				}
				echo implode(', ', $buf);
			?>
		    </h4>
		    <?php
			if( isset($album['genres']) ) {
			    echo '<p class="genre"><strong>Genres:</strong> ';
			    echo implode(", ", $album_genres);
			    echo '</p>';
			}
			?>
		    <?php if( isset( $album['tracks'] ) && $album['tracks'] ) {
			    echo '<p class="track-count">'.$album['tracks'].' tracks</p>';
		    } ?>
		    <?php if( isset( $album['release_date'] ) && $album['release_date'] ) {
			    echo '<p class="release-date"><strong>Release date:</strong> '. date( 'M j, Y', strtotime( $album['release_date'] ) ) .'</p>';
		    } ?>

<?php // add in ability to follow artist from here ?>
		  </li>
		<?php endforeach; ?>
      </ol>
      <?php //TODO: make this play nicely with filters ?>
      <?php if( ( $list_offset - $limit ) >= 0 ) { ?>
	      <a href="<?php echo $path_to_play_later; ?>/?offset=<?php echo $list_offset - $limit ?>" class="button offset prev">Previous <?php echo $limit; ?></a>
      <?php } elseif ( $list_offset !== 0 ) { ?>
	      <a href="<?php echo $path_to_play_later; ?>/?offset=0" class="button offset prev">Back to the Start</a>
      <?php } ?>
      <?php // I would have an upward bound here, but who cares? ?>
    	  <a href="<?php echo $path_to_play_later; ?>/?offset=<?php echo $list_offset + $limit ?>" class="button offset next">Next <?php echo $limit; ?></a>
    </section>

    <footer>
      <h2>Built on the open Spotify metadata API</h2>
      <p>
	This is a simple hack built on top of the open
	<a href="https://developer.spotify.com/technologies/web-api/">Spotify metadata API</a>.
	Source repo coming soon. Based on the wonderful work on <a href="http://spotifyreleases.com/">SpotifyReleases.com</a>.
	Forked and improved by <a href="https://twitter.com/MikeNGarrett">@MikeNGarrett</a>
      </p>
    </footer>
    <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script type="text/javascript" src="js/site.js"></script>
  </body>
</html>
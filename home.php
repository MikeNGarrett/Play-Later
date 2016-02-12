<?php
if( !defined('CHECK') )
	header( 'Location: '.APPURL );

error_reporting(E_ALL);
session_start();

if( isset( $_GET['destroy'] ) && $_GET['destroy'] ) {
	unset( $_GET['destroy'] );
	session_destroy();
	header( 'Location: '.APPURL );
}

include_once dirname(__FILE__).'/spotify-config.php';
$spotify = new Spotify();

include_once dirname(__FILE__).'/config.php';
$database = new Database();

include_once dirname(__FILE__).'/phpfastcache.php';
$cache = phpFastCache();

$all_user_playlists = array();

// Only start a session if we have an authorized account
$logged_in = false;

if( !empty( $_SESSION['spotify_token'] ) && !empty( $_SESSION['spotify_expires'] ) ) {
	if ( $_SESSION['spotify_expires'] < time() ) {
		if( !empty($_SESSION['spotify_refresh_token']) ) {
			$spotify->session->refreshAccessToken( $_SESSION['spotify_refresh_token'] );
			$_SESSION['spotify_token'] = $spotify->session->getAccessToken();
			$_SESSION['spotify_refresh_token'] = $spotify->session->getRefreshToken();
			$_SESSION['spotify_expires'] = $spotify->session->getTokenExpiration();

			$spotify->api->setAccessToken( $_SESSION['spotify_token'] );
		} else {
			// Kill the session
			session_unset();
		}
	} else {
		$spotify->api->setAccessToken( $_SESSION['spotify_token'] );
	}
	$me = $spotify->api->me();
	if( !isset( $_SESSION['playlist'] ) && !$cache->isExisting( $me->id.'play-later-list' ) ) {
		$test = get_playlist( $me, $spotify->api );
		if( !is_array( $test ) || empty( $test ) ) {
			error_log( "Did not find Play Later. count:". count($all_user_playlists).PHP_EOL );
			$play_later = $spotify->api->createUserPlaylist( $me->id, array( 'name' => 'Play Later' ) );
		} else {
			if( count( $test['all'] ) > 1 ) {
				$final_form = array();
				$track_record = 0;
				foreach( $test['all'] as $user_playlist ) {
					if( $user_playlist->tracks->total > $track_record ) {
						$final_form = $user_playlist;
					}
				}
				$play_later = $final_form;
			} else {
				$play_later = $test['all'][0];
			}
		}
		$_SESSION['playlist'] = $play_later;
		$cache->set( $me->id.'play-later-list', $play_later, 60*60*24*7); // Cache for 7 days
	} else {
		$_SESSION['playlist'] = $cache->get( $me->id.'play-later-list' );
	}

	$play_later = $_SESSION['playlist'];
	$logged_in = true;
	$playlist_limits = $spotify->api->getUserPlaylist( $me->id, $play_later->id, array( 'fields' => 'tracks(total)' ) );

	$playlist_tracks_cursor = 0;
	$playlist_tracks_total = $playlist_limits->tracks->total;

	$i = 0;
	$all_user_playlists_albums = array();
	while( $playlist_tracks_cursor <= $playlist_tracks_total ) {
		$i++;

		$playlist = $spotify->api->getUserPlaylistTracks( $me->id, $play_later->id, array( 'fields' => 'items(track(album(id))),limit,offset', 'limit' => 100, 'offset' => $playlist_tracks_cursor ) );
		$playlist_tracks_cursor = $playlist->offset + $playlist->limit;

		foreach( $playlist->items as $album ) {
			$all_user_playlists_albums[$album->track->album->id] = $album->track->album->id;
		}
		if($i > 50) {
			// Kill runaway processes, limited to 5000 track playlists.
			break;
		}
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

// Get rid of these if they're empty
if( isset( $_GET['date'] ) && empty( $_GET['date'] ) ) {
	unset( $_GET['date'] );
}
if( isset( $_GET['genres'] ) && empty( $_GET['genres'] ) ) {
	unset( $_GET['genres'] );
}


function get_playlist( $me, $api, $offset = 0 ) {
	$new_all_user_playlists = array();
	$all_user_playlists_playlists = $api->getUserPlaylists( $me->id, array( 'offset' => $offset ) );
	$total = $all_user_playlists_playlists->total;
	$total_pages = $total / 20;
	for($i=0;$i<=$total_pages;$i++) {
		$offset = $i*20;
		$all_user_playlists_playlists = $api->getUserPlaylists( $me->id, array( 'offset' => $offset ) );

		foreach( $all_user_playlists_playlists->items as $playlist ) {
			if( $playlist->name == 'Play Later' ) {
				$new_all_user_playlists[] = $playlist;
			}
		}
	}
	return array( 'success' => false, 'count' => $offset.' / '.$total, 'offset' => $offset, 'all' => $new_all_user_playlists );
}

function xe($s) {
	static $u = array('&', '"', '<', '>');
	static $e = array('&#38;', '&#34;', '&#60;', '&#62;');
	return str_replace($u, $e, $s);
}
$date = date('Y-m-d');

// If it's Friday, set it to today.
if( date( 'D' ) == 'Fri' ) {
	$last_friday = time();
} else {
	$last_friday = strtotime( "last Friday" );
}
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
// Required for is_numeric, that jerk
if( isset( $_GET['offset'] ) ) {
	$temp_offset = $_GET['offset'];
	if( is_numeric( $temp_offset ) ) {
		if( $temp_offset >= 0 && $temp_offset < 25000 ) {
			$list_offset = intval( $temp_offset );
		}
	}
}

$where_track_count = "";
$where_release_date = "";
$where_availability = "";
$where_genres = "";
if( isset( $_GET['genres'] ) ) {
	$get_genres = $_GET['genres'];

	// Go get synonyms
	$imp_genres = implode('|', $get_genres);
	$genre_syn = $database->prepare("SELECT mega_relation,large_relation,medium_relation,low_relation FROM genres WHERE name REGEXP ".$database->quote( $imp_genres ) );
	$genre_syn->execute();
	$syns = $genre_syn->fetchAll();

	foreach( $syns as $row ) {
		foreach( $row as $key => $syn ) {
			if( !$syn )
				continue;

			switch( $key ) {
				case "mega_relation":
					$syn_genres = maybe_unserialize( $syn );
					if( is_array( $syn_genres ) ) {
						$get_genres = array_merge( $get_genres, $syn_genres );
					}
				break;
				case "large_relation":
					$syn_genres = maybe_unserialize( $syn );
					if( is_array( $syn_genres ) ) {
						$get_genres = array_merge( $get_genres, $syn_genres );
					}
				break;
				case "medium_relation":
					$syn_genres = maybe_unserialize( $syn );
					if( is_array( $syn_genres ) ) {
						$get_genres = array_merge( $get_genres, $syn_genres );
					}
				break;
				case "mega_relation":
					$syn_genres = maybe_unserialize( $syn );
					if( is_array( $syn_genres ) ) {
						$get_genres = array_merge( $get_genres, $syn_genres );
					}
				break;
			}
		}
	}
	$get_genres = array_unique($get_genres);

	$where_genres = "AND (";
	foreach( $get_genres as $g ) {
		$where_genres .= ' genres LIKE '.$database->quote('%"'.$g.'"%').' OR';
	}
	$where_genres = rtrim( $where_genres, ' OR' );
	$where_genres .= ")";
} else {
	$where_track_count = "( tracks > 3 AND tracks < 25 ) AND ";
	$where_release_date = "( release_date BETWEEN :lastfriday AND :thisfriday )";
//	$where_availability = "( availability LIKE '%US%' OR availability='ANY' )";
}

$query = $database->prepare("SELECT DISTINCT * FROM albums WHERE " . $where_track_count . $where_release_date ." AND type='album' ". $where_genres . " GROUP BY(artists) ORDER BY popularity DESC LIMIT :offset, :limit");
if( !isset( $_GET['genres'] ) ) {
	$query->bindParam(':lastfriday', $previous_date, PDO::PARAM_STR);
	$query->bindParam(':thisfriday', $next_date, PDO::PARAM_STR);
}
$query->bindParam(':offset', $list_offset, PDO::PARAM_INT);
$query->bindParam(':limit', $limit, PDO::PARAM_INT);
$query->execute();

$albums = $query->fetchAll();

foreach( $albums as $key => &$album ) {
	$get_artists = maybe_unserialize( $album['artists'] );

	if( !is_array( $get_artists ) ) {
		continue;
	} else {
		$album['artists'] = $get_artists;
	}


/*
	$album['artists'] = array();

	foreach( $get_artists as &$artist ) {
		$query = $database->prepare('SELECT name FROM artists WHERE id=:id');
		$query->bindParam(':id', $artist->id, PDO::PARAM_STR);
		$query->execute();
		$artist_names = $query->fetchAll();

		$album['artists'][$artist->id] = $artist_names[0];
	}
*/
}
//unset($album);

// TODO: figure out what the hell to exactly do with these currently useless genres

if( $cache->isExisting("genres") ) {
	$select_genres = $cache->get("genres");

} else {
	$genre_query = $database->prepare("SELECT name FROM genres");
	$genre_query->execute();

	$select_genres = array();
	while ($row = $genre_query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
		$genre = $row[0];
		$url_genres = xe($genre);
		$selected = '';
		$count_genre_query = $database->prepare("SELECT COUNT(*) FROM albums WHERE ( genres LIKE :genre ) AND type='album' AND ( release_date BETWEEN :lastfriday AND :thisfriday )");
		// A way to check for an exact match in a serialized array
		$p_genre = '%"'.$genre.'"%';
		$count_genre_query->bindParam(':genre', $p_genre, PDO::PARAM_STR);

		if( date( 'D' ) == 'Fri' ) {
			$last_friday = date( 'Y-m-d' );
		} else {
			$last_friday = date( 'Y-m-d', strtotime( "last Friday" ) );
		}
		$today = date( 'Y-m-d' );

		$count_genre_query->bindParam(':lastfriday', $last_friday, PDO::PARAM_STR);
		$count_genre_query->bindParam(':thisfriday', $today, PDO::PARAM_STR);
		$count_genre_query->execute();

		$genre_count = $count_genre_query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT);
		$genre_count = $genre_count[0];
/* // TODO: How do we determine what's selected?
		if( isset( $_GET['genres'] ) && in_array( $genre, $_GET['genres'] ) ) {
			$selected = ' selected';
		}
*/
		if( $genre_count > 1 ) {
			$select_genres[$genre] = '<option value="'.$url_genres.'"'.$selected.'>'.$genre.' ('.$genre_count.')</option>';
		}
	}
	if( empty( $select_genres ) ) {
		$select_genres = "nope";
	} else {
		asort( $select_genres );
	}

	$cache->set("genres", $select_genres, 60*60*12); // Cache for 12 hours
}
?>
<!DOCTYPE HTML>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="title" content="Play Later: Save New Music Releases for Later (Page <?php echo ( $list_offset / $limit ) + 1; ?>)">
    <meta name="description" content="All new album releases published on Spotify presented similar to Rdio's new albums list, updated every New Music Friday. Never miss another new album.">
    <link rel="canonical" href="<?php echo APPURL; ?>" />

    <meta property="og:url" content="<?php echo APPURL; ?>"/>
    <meta property="og:title" content="Play Later: Save New Music Releases for Later."/>
    <meta property="og:description" content="All new album releases published on Spotify presented similar to Rdio's new albums list, updated every New Music Friday. Never miss another new album."/>

    <title>Play Later: Save New Music Releases for Later (Page <?php echo ( $list_offset / $limit ) + 1; ?>)</title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans:400,700,600,600italic,800' rel='stylesheet' type='text/css'>
	<link type="text/css" href="/css/select2.min.css" rel="stylesheet" />
	<link type="text/css" href="/css/site.css" rel="stylesheet" />
  </head>

  <body>
    <header>
      <h1><a href="<?php echo APPURL; ?>">Play Later: Browse newly released albums and save them for later</a></h1>
      <?php if( !$logged_in ) { ?>
        <a href="/spotify/" class="button log-in-button">Log In to Spotify</a>
      <?php } else { ?>
	    <a href="?destroy=1" class="button log-in-button">Logout</a>
      <?php } ?>
	  <?php if( isset( $_GET['genres'] ) ) { ?>
	    <h2>Showing the all albums in genres: <?php echo implode( ', ', $get_genres ); ?></h2>
	  <?php } else { ?>
	    <h2>Showing the most popular released albums on <a href="http://spotify.com/" target="_blank">Spotify</a>.</h2>
	    <h3>Current limits: Releases since last Friday (<?php echo $previous_date; ?>), no singles, no compilations.</h2>
	  <?php } ?>
      <p>
	      Want to remove the filters? Browse the full, unfiltered list on <a href="<?php echo APPURL; ?>music-bin/">The Music Bin</a>.
      </p>
	  <p>Once you log in to Spotify, The Play Later buttons add the selected album to a new (or existing) playlist called &ldquo;Play Later&rdquo;</p>

	  <form action="/" method="get">
	  	<?php if( $select_genres != "nope") { ?>
		<select id="genres" name="genres[]" multiple="multiple" size="10">
			<?php echo implode("", $select_genres); ?>
		</select>
		<?php } ?>
		<select name="date" class="date-select">
			<option value="">-- Date Range --</option>
			<option value="this-week"<?php if( isset( $_GET['date'] ) && $_GET['date'] == 'this-week' ) { echo ' selected'; } ?>>This Week</option>
			<option value="last-week"<?php if( isset( $_GET['date'] ) && $_GET['date'] == 'last-week' ) { echo ' selected'; } ?>>Last Week</option>
			<option value="two-weeks"<?php if( isset( $_GET['date'] ) && $_GET['date'] == 'two-weeks' ) { echo ' selected'; } ?>>Two Weeks Ago</option>
		</select>
		<button type="submit" class="button">Filter</button>
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
      <ol class="albums">
		<?php foreach ($albums as $album): ?>
		  <?php
			$album_genres = array();
			if( isset($album['genres']) ) {
			    $album_genres = maybe_unserialize( $album['genres'] );
			}
		    if( isset( $_GET['genres'] ) && !empty( $_GET['genres'] ) ) {
			    $passed_genres = $_GET['genres'];
			    if( empty( $album['genres'] ) || empty( array_intersect( $passed_genres, $album_genres ) ) ) {
				    continue;
				}
			}
		  ?>
		  <li class="album <?php echo $album['availability']; ?>">
		  	<p>
			  	<a class="name" href="spotify:album:<?php echo $album['id']; ?>">
				  	<img src="<?php echo $album['image']; ?>" alt="<?php echo $album['name']; ?>"/>
				</a>
			</p>
		    <?php /* <p class="no"><?php echo $no+1?></p> */ ?>
		    <?php if( $logged_in ) { ?>
		    	<?php if( !array_search( $album['id'], $all_user_playlists_albums ) ) { ?>
			    <a href="/spotify/add-tracks/<?php echo $album['id']; ?>/" data-album="<?php echo $album['id']; ?>" class="button play-later" target="_blank">Play Later</a>
			    <?php } else { ?>
					<p class="status">Album already in playlist</p>
			    <?php } ?>
		    <?php } ?>
			<h3>
		      <a class="name" href="spotify:album:<?php echo $album['id']; ?>" title="Album Availability: <?php echo $album['availability']; ?>">
			      <?php echo $album['name']; ?>
			  </a>
		    </h3>
		    <h4>
			<?php
				$buf=array();
				if( isset( $album['artists'] ) && !empty( $album['artists'] ) && !is_serialized( $album['artists'] ) ) {
					foreach ($album['artists'] as $artist) {
						$buf[] = '<a class="artist" href="'.$artist->uri.'">'.$artist->name.'</a>';
					}
					echo implode(', ', $buf);
				} else {
					echo '<p class="artist">No Artist Data Available</p>';
				}
			?>
		    </h4>
		    <?php
			if( isset($album['genres']) && is_array( $album_genres ) && !empty($album_genres) ) {
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
		    <p><small><a href="<?php echo APPURL; ?>album/<?php echo $album['id']; ?>/" target="_blank">Full &ldquo;<?php echo $album['name']; ?>&rdquo; details</a></small></p>

<?php // add in ability to follow artist from here ?>
		  </li>
		<?php endforeach; ?>
      <?php
		  $query_args = '';
		  if ( !empty($_GET) ) {
		    foreach ($_GET as $parameter => $value) {
			  if( $parameter == 'offset' || $parameter == 'q' ) continue;
			  if( is_array( $value ) ) {
				foreach( $value as $v ) {
					$query_args .= "&" . $parameter . "%5B%5D=" . urlencode($v);
				}
			  } else {
			  	$query_args .= "&" . $parameter . "=" . urlencode($value);
			  }
		    }
		  }

	      if( ( $list_offset - $limit ) >= 0 ) { ?>
	      <a href="?offset=<?php echo $list_offset - $limit; echo $query_args; ?>" class="button offset prev">Previous <?php echo $limit; ?></a>

      <?php } elseif ( $list_offset !== 0 ) { ?>
	      <a href="?offset=0" class="button offset prev">Back to the Start</a>
      <?php } ?>
      <?php // I would have an upward bound here, but who cares? ?>
	      <span class="page-number">Page <?php echo ( $list_offset / $limit ) + 1; ?></span>
    	  <a href="?offset=<?php echo $list_offset + $limit; echo $query_args; ?>" class="button offset next">Next <?php echo $limit; ?></a>
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
			echo 'Ingested '.count( $all_user_playlists_albums ).' albums from <a href="'.$play_later->uri.'">Play Later</a>, ';
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
    <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
	<script type="text/javascript" src="/js/select2.min.js"></script>
    <script type="text/javascript" src="/js/site.js"></script>
	<script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

	ga('create', 'UA-71716026-1', 'auto');
	ga('send', 'pageview');

	</script>
  </body>
</html>
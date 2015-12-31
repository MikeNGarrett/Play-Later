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
error_reporting(E_ALL);
session_start();

include_once 'spotify-config.php';
$spotify = new Spotify();

include_once 'config.php';
$database = new Database();

require_once("phpfastcache.php");
$cache = phpFastCache();

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

  if( !isset( $_SESSION['playlist'] ) ) {
    $play_later = get_playlist( $me, $spotify->api );
    if( !$play_later ) {
      $play_later = $spotify->api->createUserPlaylist( $me->id, array( 'name' => 'Play Later' ) );
    }
    $_SESSION['playlist'] = $play_later;
  }
  $play_later = $_SESSION['playlist'];
  $logged_in = true;

  if( $cache->isExisting($play_later->id."play_later_albums") ) {
    $all_albums = $cache->get($play_later->id."play_later_albums");
  } else {

    $playlist_limits = $spotify->api->getUserPlaylist( $me->id, $play_later->id, array( 'fields' => 'tracks(total)' ) );

    $playlist_tracks_cursor = 0;
    $playlist_tracks_total = $playlist_limits->tracks->total;

    $i = 0;
    $all_albums = array();
    while( $playlist_tracks_cursor <= $playlist_tracks_total ) {
      $i++;

      $playlist = $spotify->api->getUserPlaylistTracks( $me->id, $play_later->id, array( 'fields' => 'items(track(album(id))),limit,offset', 'limit' => 100, 'offset' => $playlist_tracks_cursor ) );
      $playlist_tracks_cursor = $playlist->offset + $playlist->limit;

      foreach( $playlist->items as $album ) {
        $all_albums[$album->track->album->id] = $album->track->album->id;
      }
      if($i > 50) {
        // Kill runaway processes, limited to 5000 track playlists.
        break;
      }
    }
    $cache->set($play_later->id."play_later_albums", $all_albums, 60*15); // Cache for 15 minutes
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
      $list_offset = (int) $temp_offset;
    }
  }
}

$where_track_count = "";
$where_release_date = "";
$where_availability = "";
$where_genres = "";
if( isset( $_GET['genres'] ) ) {
  $where_genres = "(";
  foreach( $_GET['genres'] as $g ) {
    $where_genres .= ' genres LIKE '.$database->quote('%"'.$g.'"%').' OR';
  }
  $where_genres = rtrim( $where_genres, ' OR' );
  $where_genres .= ")";
} else {
  $where_track_count = "( tracks > 3 AND tracks < 25 ) AND ";
  $where_release_date = "( release_date BETWEEN :lastfriday AND :thisfriday ) AND ";
  $where_availability = "( availability LIKE '%US%' OR availability='ANY' )";
}

$query = $database->prepare("SELECT * FROM albums WHERE " . $where_track_count . $where_release_date . $where_availability . $where_genres . " ORDER BY popularity DESC LIMIT :offset, :limit");
if( !isset( $_GET['genres'] ) ) {
  $query->bindParam(':lastfriday', $previous_date, PDO::PARAM_STR);
  $query->bindParam(':thisfriday', $next_date, PDO::PARAM_STR);
}
$query->bindParam(':offset', $list_offset, PDO::PARAM_INT);
$query->bindParam(':limit', $limit, PDO::PARAM_INT);
$query->execute();

// Only returns 100
// TODO: grab all rows or something and actually show an accurate count
//$album_count = $query->rowCount();
$albums = $query->fetchAll();

foreach( $albums as $key => &$album ) {
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
    $count_genre_query = $database->prepare("SELECT COUNT(*) FROM albums WHERE ( genres LIKE :genre ) AND ( tracks > 3 AND tracks < 25 )");
    // A way to check for an exact match in a serialized array
    $p_genre = '%"'.$genre.'"%';
    $count_genre_query->bindParam(':genre', $p_genre, PDO::PARAM_STR);
    $count_genre_query->execute();

    $genre_count = $count_genre_query->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT);
    $genre_count = $genre_count[0];
/* // TODO: figure out what the hell to do here
    if( isset( $_GET['genres'] ) && in_array( $genre, $_GET['genres'] ) ) {
      $selected = ' selected';
    }
*/
    if( $genre_count > 0 ) {
      $select_genres[$genre] = '<option value="'.$url_genres.'"'.$selected.'>'.$genre.' ('.$genre_count.')</option>';
    }
  }
  asort( $select_genres );
  $cache->set("genres", $select_genres, 60*60*12); // Cache for 12 hours
}

?>
<!DOCTYPE HTML>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="title" content="Play Later - Spotify New Releases">
    <meta name="description" content="New releases on Spotify, Rdio style">

    <meta property="og:url" content="http://www.play-later.com"/>
    <meta property="og:title" content="Play Later - Spotify New Releases"/>
    <meta property="og:description" content="New releases on Spotify, Rdio style"/>

    <title>New releases on Spotify, Rdio style</title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans:400,700,600,600italic,800' rel='stylesheet' type='text/css'>
    <link type="text/css" href="css/select2.min.css" rel="stylesheet" />
    <link type="text/css" href="css/site.css" rel="stylesheet" />
  </head>

  <body>
    <header>
      <h1>New releases on <a href="http://www.spotify.com/">Spotify</a></h1>
      <?php if( !$logged_in ) { ?>
        <a href="spotify/" class="button log-in-button">Log In</a>
      <?php } ?>
      <?php if( isset( $_GET['genres'] ) ) { ?>
        <h2>Showing the all albums in genres: <?php implode( ', ', $_GET['genres'] ); ?></h2>
      <?php } else { ?>
        <h2>Showing the most popular released albums since last Friday (<?php echo date( 'm/d/Y', $last_friday ); ?>), no singles, no compilations</h2>
      <?php } ?>
        <h3>Use the filters below to modify the result.</h3>
      <p>The Play Later buttons will add the selected album to a new (or existing) playlist called &ldquo;Play Later&rdquo;</p>

      <form action="index.php" method="get">
        <select id="genres" name="genres[]" multiple="multiple" size="10">
          <?php echo implode("", $select_genres); ?>
        </select>
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
            <p>
              <a class="name" href="spotify:album:<?php echo $album['id']; ?>">
                <img src="<?php echo $album['image']; ?>" alt="<?php echo $album['name']; ?>"/>
              </a>
            </p>
            <?php /* <p class="no"><?php echo $no+1?></p> */ ?>
            <?php if( $logged_in ) { ?>
              <?php if( !array_search( $album['id'], $all_albums ) ) { ?>
              <a href="spotify/addTracks.php?album=<?php echo $album['id']; ?>" data-album="<?php echo $album['id']; ?>" class="button play-later" target="_blank">Play Later</a>
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

      <?php
        // TODO: build offset into other query strings, eg offset=100&date=this-week
        if( ( $list_offset - $limit ) >= 0 ) { ?>
        <a href="?offset=<?php echo $list_offset - $limit ?>" class="button offset prev">Previous <?php echo $limit; ?></a>

      <?php } elseif ( $list_offset !== 0 ) { ?>
        <a href="?offset=0" class="button offset prev">Back to the Start</a>
      <?php } ?>
      <?php // I would have an upward bound here, but who cares? ?>
        <a href="?offset=<?php echo $list_offset + $limit ?>" class="button offset next">Next <?php echo $limit; ?></a>
    </section>

    <footer>
      <h2>Built on the open Spotify metadata API</h2>
      <p>
        DEBUG:
        <?php
        if( isset( $me ) ) {
          echo 'Logged in as <a href="'.$me->href.'">'.$me->display_name.'</a>, ';
        }
        if( isset( $all_albums ) ) {
          echo 'Ingested '.count( $all_albums ).' albums from Play Later, ';
        }
        if( isset( $_SESSION['spotify_expires'] ) ) {
          echo 'Token Expires: '.date('Y-m-d h:m:s', $_SESSION['spotify_expires']).', ';
        }

        ?>
      </p>
      <p>
        This is a simple hack built on top of the open
        <a href="https://developer.spotify.com/technologies/web-api/">Spotify metadata API</a>.
        Source repo coming soon. Based on the wonderful work on <a href="http://spotifyreleases.com/">SpotifyReleases.com</a>.
        Forked and improved by <a href="https://twitter.com/MikeNGarrett">@MikeNGarrett</a>
      </p>
      <p>
        Other sources: <a href="http://everynoise.com/spotify_new_releases.html">EveryNoise New Releases</a>, <a href="http://swarm.fm/">Swarm.fm</a>, and <a href="http://pansentient.com/new-on-spotify/">Pansentient's New on Spotify</a>.
      </p>
      <p>
        <a href="http://everynoise.com/engenremap.html">List of all Spotify genres</a>
      </p>
    </footer>

    <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script type="text/javascript" src="js/select2.min.js"></script>
    <script type="text/javascript" src="js/site.js"></script>

  </body>
</html>

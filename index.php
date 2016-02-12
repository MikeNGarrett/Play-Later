<?php
define('CHECK', true);

include_once 'AltoRouter.php';
$router = new AltoRouter();

// Home
$router->map( 'GET|POST', '/', function() {
    require __DIR__ . '/home.php';
});

// The Music Bin
$router->map( 'GET|POST', '/music-bin/?', function() {
	require __DIR__ . '/all.php';
});

// Single Album Pages
$router->map( 'GET', '/album/?([a:id])?/?', function( $id ) {
	require __DIR__ . '/single.php';
});
$router->map( 'GET|POST', '/spotify/?', function() {
	require __DIR__ . '/spotify/index.php';
});
$router->map( 'GET', '/spotify/add-tracks/?([a:id])?/?', function( $id ) {
	require __DIR__ . '/spotify/addTracks.php';
});



$match = $router->match();

if( $match && is_callable( $match['target'] ) ) {
	call_user_func_array( $match['target'], $match['params'] );
} else {
	// no route was matched
	echo '<h1>404 Not Found</h1>';
	header( $_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
}
?>
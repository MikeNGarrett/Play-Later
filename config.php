<?php
#
# Play Later, originally based off of code from "New releases on Spotify"
#
# A list of new releases from Spotify, coupled with Last.fm data,
# displayed like Rdio, and with add to playlist functionality.
#
# MIT license (basically do what you want as long as this license is reproduced):
#
# Copyright (c) 2010-2014 Rasmus Andersson, Markus Persson
# Copyright (c) 2015-2016 Mike N Garrett
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

define('APPURL', ''); // example: http://play-later.com/
class Database extends PDO
{
    private $host;
    private $database;
    private $user;
    private $password;
    private $dns;

    /**
     * Constructor
     */
    public function __construct()
    {
		$this->host = 'HOST';
		$this->database = 'DATABASE';
		$this->user = 'DATABASE_USER';
		$this->password = 'DATABASE_PASSWORD';

        $this->dns = 'mysql:host=' . $this->host . ';dbname=' . $this->database;

        try
        {
            parent::__construct($this->dns, $this->user, $this->password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        }
        catch (PDOException $e)
        {
            error_log( 'Mysql connection error: '.$e->getMessage() );
            die();
        }

        $this->create_database();

    }
    private function create_database() {
	    try
	    {
		    $this->exec('
				CREATE TABLE IF NOT EXISTS albums (
					id varchar( 128 ) NOT NULL,
					name varchar( 256 ) NOT NULL,
					release_date DATE NOT NULL,
					availability varchar( 128 ),
					popularity mediumint,
					tracks int,
					image varchar( 256 ),
					artist_images varchar( 1024 ),
					artists varchar( 1024 ),
					genres varchar( 512 ),
					PRIMARY KEY ( id ),
					UNIQUE KEY ( UPC ),
					type varchar( 128 ),
					mbid varchar( 64 )
				);
			');
		}
		catch ( PDOException $e ) {
			error_log( 'Create table error: '. $e->getMessage() );
		}
	    try
	    {
			$this->exec('
				CREATE TABLE IF NOT EXISTS artists (
					id varchar( 128 ) NOT NULL,
					name varchar( 256 ) NOT NULL,
					PRIMARY KEY ( id )
				);
			');
		}
		catch ( PDOException $e ) {
			error_log( 'Create table error: '. $e->getMessage() );
		}
	    try
	    {
			$this->exec('
				CREATE TABLE IF NOT EXISTS genres (
					id MEDIUMINT NOT NULL AUTO_INCREMENT,
					name varchar( 128 ) NOT NULL,
					PRIMARY KEY ( id ),
					UNIQUE KEY ( name )
				);
			');
		}
		catch ( PDOException $e ) {
			error_log( 'Create table error: '. $e->getMessage() );
		}
	}
}
function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return false;
}
function is_serialized( $data, $strict = true ) {
	// if it isn't a string, it isn't serialized.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
 	if ( 'N;' == $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	if ( $strict ) {
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	} else {
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		// Either ; or } must exist.
		if ( false === $semicolon && false === $brace )
			return false;
		// But neither must be in the first X characters.
		if ( false !== $semicolon && $semicolon < 3 )
			return false;
		if ( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( $strict ) {
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			} elseif ( false === strpos( $data, '"' ) ) {
				return false;
			}
			// or else fall through
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}
function check_dir( $dir ) {
	if ( !file_exists( $dir ) ) {
		$check = mkdir( $dir, 0775, true ); // sets permissions and creates directories recursively
		if ( !$check ) {
			error_log( 'Could not create directory: ', $dir );
			return false;
		}
	}
	return $dir;
}
?>
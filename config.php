<?php
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
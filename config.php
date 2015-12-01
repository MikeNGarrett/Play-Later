<?php
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
					id varchar(128) NOT NULL,
					upc varchar(128) NOT NULL,
					name varchar(256) NOT NULL,
					release_date DATE NOT NULL,
					availability varchar(128),
					popularity mediumint,
					tracks int,
					image varchar(256),
					artists varchar(256) NOT NULL,
					genres varchar(128),
					PRIMARY KEY ( id ),
					UNIQUE KEY ( UPC )
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
					id varchar(128) NOT NULL,
					name varchar(256) NOT NULL,
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
					name varchar(128) NOT NULL,
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
?>
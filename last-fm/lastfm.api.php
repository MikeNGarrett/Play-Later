<?php

/** Autoloads PHP last.fm API classes
 *
 * @package	php-lastfm-api
 * @author  Felix Bruns <felixbruns@web.de>
 * @version	1.0
 */

function lastfm_autoload($name){
    if(stripos($name, 'LastFM_') === false)
        return false;
    
    if($name == 'LastFM_Cache')
        $filename = realpath(sprintf("%s/cache/%s.php", dirname(__FILE__), 'Cache'));
    else if(stripos($name, 'LastFM_Cache_') !== false)
		$filename = realpath(sprintf("%s/cache/%s.php", dirname(__FILE__), str_replace('LastFM_Cache_', '', $name)));
    else if($name == 'LastFM_Caller')
        $filename = realpath(sprintf("%s/caller/%s.php", dirname(__FILE__), 'Caller'));
	else if(stripos($name, 'LastFM_Caller_') !== false)
		$filename = realpath(sprintf("%s/caller/%s.php", dirname(__FILE__), str_replace('LastFM_Caller_', '', $name)));
	else 
		$filename = realpath(sprintf("%s/%s.php", dirname(__FILE__), str_replace('LastFM_', '', $name)));

	if(!file_exists($filename))
        return false;
    
    require_once $filename;
}

spl_autoload_register('lastfm_autoload');
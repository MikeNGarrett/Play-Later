<?php

/** Provides different methods to query group information.
 *
 * @package	php-lastfm-api
 * @author  Patrick Galbraith, Felix Bruns
 * @version	1.0
 */
class LastFM_Chart {
    
    public static function getHypedArtists($limit = 50){
        $xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('chart.getHypedArtists', array(
            'limit' => $limit
        ));

		$artists = array();

		foreach($xml->children() as $artist){
			$artists[] = LastFM_Artist::fromSimpleXMLElement($artist);
		}

		$perPage = LastFM_Util::toInteger($xml['perPage']);

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($xml['totalPages']) * $perPage,
			LastFM_Util::toInteger($xml['page']) * $perPage,
			$perPage,
			$artists
		);
    }
    
    public static function getHypedTracks($limit = 50){
        $xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('chart.getHypedTracks', array(
            'limit' => $limit
        ));

		$tracks = array();

		foreach($xml->children() as $track){
			$tracks[] = LastFM_Track::fromSimpleXMLElement($track);
		}

		$perPage = LastFM_Util::toInteger($xml['perPage']);

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($xml['totalPages']) * $perPage,
			LastFM_Util::toInteger($xml['page']) * $perPage,
			$perPage,
			$tracks
		);
    }
    
    public static function getLovedTracks($limit = 50){
        $xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('chart.getLovedTracks', array(
            'limit' => $limit
        ));

		$tracks = array();

		foreach($xml->children() as $track){
			$tracks[] = LastFM_Track::fromSimpleXMLElement($track);
		}

		$perPage = LastFM_Util::toInteger($xml['perPage']);

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($xml['totalPages']) * $perPage,
			LastFM_Util::toInteger($xml['page']) * $perPage,
			$perPage,
			$tracks
		);
    }
    
    public static function getTopArtists($limit = 50){
        $xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('chart.getTopArtists', array(
            'limit' => $limit
        ));

		$artists = array();

		foreach($xml->children() as $artist){
			$artists[] = LastFM_Artist::fromSimpleXMLElement($artist);
		}

		$perPage = LastFM_Util::toInteger($xml['perPage']);

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($xml['totalPages']) * $perPage,
			LastFM_Util::toInteger($xml['page']) * $perPage,
			$perPage,
			$artists
		);
    }
    
    public static function getTopTags($limit = 50){
        $xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('chart.getTopTags', array(
            'limit' => $limit
        ));

		$tags = array();

		foreach($xml->children() as $tag){
			$tags[] = LastFM_Tag::fromSimpleXMLElement($tag);
		}

		$perPage = LastFM_Util::toInteger($xml['perPage']);

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($xml['totalPages']) * $perPage,
			LastFM_Util::toInteger($xml['page']) * $perPage,
			$perPage,
			$tags
		);
    }
    
    public static function getTopTracks($limit = 50){
        $xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('chart.getTopTracks', array(
            'limit' => $limit
        ));

		$tracks = array();

		foreach($xml->children() as $track){
			$tracks[] = LastFM_Track::fromSimpleXMLElement($track);
		}

		$perPage = LastFM_Util::toInteger($xml['perPage']);

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($xml['totalPages']) * $perPage,
			LastFM_Util::toInteger($xml['page']) * $perPage,
			$perPage,
			$tracks
		);
    }
    
}
<?php

/** Represents a track and provides different methods to query track information.
 *
 * @package	php-lastfm-api
 * @author  Felix Bruns <felixbruns@web.de>
 * @version	1.0
 */
class LastFM_Track extends LastFM_Media {
	/** The artist of this track.
	 *
	 * @var mixed
	 * @access	private
	 */
	private $artist;

	/** The album of this track.
	 *
	 * @var mixed
	 * @access	private
	 */
	private $album;

	/** The tracks duration.
	 *
	 * @var integer
	 * @access	private
	 */
	private $duration;

	/** The tracks top tags.
	 *
	 * @var array
	 * @access	private
	 */
	private $topTags;

	/** The tracks id.
	 *
	 * @var integer
	 * @access	private
	 */
	private $id;

	/** The tracks location.
	 *
	 * @var string
	 * @access	private
	 */
	private $location;

	/** Indicates if this track is streamable.
	 *
	 * @var boolean
	 * @access	private
	 */
	private $streamable;

	/** Indicates if this track is a full streamable track.
	 *
	 * @var boolean
	 * @access	private
	 */
	private $fullTrack;

	/** The tracks wiki information.
	 *
	 * @var string
	 * @access	private
	 */
	private $wiki;

	/** The unix timestamp indicating when this track was last played.
	 *
	 * @var string
	 * @access	private
	 */
	private $lastPlayed;

	/** Create an album object.
	 *
	 * @param mixed		$artist		An artist object or string.
	 * @param mixed		$album		An album object or string.
	 * @param string	$name		Name of this track.
	 * @param string	$mbid		MusicBrainz ID of this track.
	 * @param string	$url		Last.fm URL of this track.
	 * @param array		$images		An array of cover art images of different sizes.
	 * @param integer	$listeners	Number of listeners of this track.
	 * @param integer	$playCount	Play count of this album.
	 * @param integer	$duration	Duration of this track.
	 * @param array		$topTags	An array of top tags of this track.
	 * @param integer	$id			ID of this track.
	 * @param string	$location	Location of this track.
	 * @param boolean	$streamable	Track is streamable.
	 * @param boolean	$fullTrack	Track is a full streamable track.
	 * @param string	$wiki		Wiki data of this track.
	 * @param integer	$lastPlayed	When this track was last played.
	 *
	 * @access	public
	 */
	public function __construct($artist, $album, $name, $mbid, $url,
								array $images, $listeners, $playCount,
								$duration, array $topTags, $id, $location,
								$streamable, $fullTrack, $wiki, $lastPlayed){
		parent::__construct($name, $mbid, $url, $images, $listeners, $playCount);

		$this->artist     = $artist;
		$this->album      = $album;
		$this->duration   = $duration;
		$this->topTags    = $topTags;
		$this->id         = $id;
		$this->location   = $location;
		$this->streamable = $streamable;
		$this->fullTrack  = $fullTrack;
		$this->wiki       = $wiki;
		$this->lastPlayed = $lastPlayed;
	}

	/** Returns the artist of this track.
	 *
	 * @return	mixed	An {@link de.felixbruns.lastfm.Artist Artist} object or the artists name.
	 * @access	public
	 */
	public function getArtist(){
		return $this->artist;
	}
    
    public function setArtist($artist){
		$this->artist = $artist;
	}

	/** Returns the album of this track.
	 *
	 * @return	mixed	An {@link de.felixbruns.lastfm.Album Album} object or the albums name.
	 * @access	public
	 */
	public function getAlbum(){
		return $this->album;
	}
    
    public function setAlbum($album){
		$this->album = $album;
	}

	/** Returns the duration of this track.
	 *
	 * @return	integer	The duration of this track.
	 * @access	public
	 */
	public function getDuration(){
		return $this->duration;
	}

	/** Returns the tracks top tags.
	 *
	 * @return	array	An array of Tag objects.
	 * @access	public
	 * @see		getTopTags
	 */
	public function getTrackTopTags(){
		return $this->topTags;
	}

	/** Returns the ID of this track.
	 *
	 * @return	integer	The ID of this track.
	 * @access	public
	 */
	public function getId(){
		return $this->id;
	}

	/** Returns the location of this track.
	 *
	 * @return	string	The location of this track.
	 * @access	public
	 */
	public function getLocation(){
		return $this->location;
	}

	/** Returns if this track is streamable.
	 *
	 * @return	boolean	A boolean.
	 * @access	public
	 */
	public function isStreamable(){
		return $this->streamable;
	}

	/** Returns if this track is a full streamable track.
	 *
	 * @return	boolean	A boolean.
	 * @access	public
	 */
	public function isFullTrack(){
		return $this->fullTrack;
	}

	/** Returns the wiki data of this track.
	 *
	 * @return	string	Wiki data.
	 * @access	public
	 */
	public function getWiki(){
		return $this->wiki;
	}

	/** Returns the unix timestamp indication when this track was last played.
	 *
	 * @return	integer	A unix timestamp.
	 * @access	public
	 */
	public function getLastPlayed(){
		return $this->lastPlayed;
	}

	/** Tag an album using a list of user supplied tags.
	 *
	 * @param	string	$artist		The artist name in question. (Required)
	 * @param	string	$track		The track name in question. (Required)
	 * @param	array	$tags		A comma delimited list of user supplied tags to apply to this track. Accepts a maximum of 10 tags. (Required)
	 * @param	Session	$session	A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Required)
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function addTags($artist, $track, array $tags, $session){
		LastFM_Caller_CallerFactory::getDefaultCaller()->signedCall('track.addTags', array(
			'artist' => $artist,
			'track'  => $track,
			'tags'   => implode(',', $tags)
		), $session, 'POST');
	}

	/** Ban a track for a given user profile. This needs to be supplemented with a scrobbling submission containing the 'ban' rating (see the audioscrobbler API).
	 *
	 * @param	string	$artist		An artist name. (Required)
	 * @param	string	$track		A track name. (Required)
	 * @param	Session	$session		A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Required)
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function ban($artist, $track, $session){
		LastFM_Caller_CallerFactory::getDefaultCaller()->signedCall('track.ban', array(
			'artist' => $artist,
			'track'  => $track
		), $session, 'POST');
	}

	/** Get the metadata for a track on last.fm using the artist/track name or a MusicBrainz id.
	 *
	 * @param	string	$artist	The artist name in question. (Optional)
	 * @param	string	$track	The track name in question. (Optional)
	 * @param	string	$mbid	The MusicBrainz ID for the track. (Optional)
	 * @return	array			A Track object.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function getInfo($artist, $track, $mbid = null){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('track.getInfo', array(
			'artist' => $artist,
			'track'  => $track,
			'mbid'   => $mbid
		));

		return LastFM_Track::fromSimpleXMLElement($xml);
	}

	/** Get the similar tracks for this track on last.fm, based on listening data.
	 *
	 * @param	string	$artist	The artist name in question. (Optional)
	 * @param	string	$track	The track name in question. (Optional)
	 * @param	string	$mbid	The MusicBrainz ID for the track. (Optional)
	 * @return	array			An array of Track objects.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function getSimilar($artist, $track, $mbid = null, $autocorrect = 0, $limit = 50){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('track.getSimilar', array(
			'artist' => $artist,
			'track'  => $track,
			'mbid'   => $mbid,
            'autocorrect' => $autocorrect,
            'limit'   => $limit
		));

		$tracks = array();

		foreach($xml->children() as $track){
			$tracks[] = LastFM_Track::fromSimpleXMLElement($track);
		}

		return $tracks;
	}

	/** Get the tags applied by an individual user to a track on last.fm.
	 *
	 * @param	string	$artist	The artist name in question. (Required)
	 * @param	string	$track	The track name in question. (Required)
	 * @param	Session	$session	A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Required)
	 * @return	array			An array of Tag objects.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function getTags($artist, $track, $session){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->signedCall('track.getTags', array(
			'artist'  => $artist,
			'track'   => $track
		), $session);

		$tags = array();

		foreach($xml->children() as $tag){
			$tags[] = LastFM_Tag::fromSimpleXMLElement($tag);
		}

		return $tags;
	}

	/** Get the top fans for this track on last.fm, based on listening data. Supply either track & artist name or MusicBrainz id.
	 *
	 * @param	string	$artist	The artist name in question. (Optional)
	 * @param	string	$track	The track name in question. (Optional)
	 * @param	string	$mbid	The MusicBrainz ID for the track. (Optional)
	 * @return	array			An array of User objects.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function getTopFans($artist, $track, $mbid = null){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('track.getTopFans', array(
			'artist' => $artist,
			'track'  => $track,
			'mbid'   => $mbid
		));

		$users = array();

		foreach($xml->children() as $user){
			$users[] = LastFM_User::fromSimpleXMLElement($user);
		}

		return $users;
	}

	/** Get the top tags for this track on last.fm, ordered by tag count. Supply either track & artist name or mbid.
	 *
	 * @param	string	$artist	The artist name in question. (Optional)
	 * @param	string	$track	The track name in question. (Optional)
	 * @param	string	$mbid	The MusicBrainz ID for the track. (Optional)
	 * @return	array			An array of Tag objects.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function getTopTags($artist, $track, $mbid = null){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('track.getTopTags', array(
			'artist' => $artist,
			'track'  => $track,
			'mbid'   => $mbid
		));

		$tags = array();

		foreach($xml->children() as $tag){
			$tags[] = LastFM_Tag::fromSimpleXMLElement($tag);
		}

		return $tags;
	}

	/** Love a track for a user profile. This needs to be supplemented with a scrobbling submission containing the 'love' rating (see the audioscrobbler API).
	 *
	 * @param	string	$artist		An artist name. (Required)
	 * @param	string	$track		A track name. (Required)
	 * @param	Session	$session	A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Required)
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function love($artist, $track, $session){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->signedCall('track.love', array(
			'artist' => $artist,
			'track'  => $track
		), $session, 'POST');

		return $xml;
	}

	/** Remove a user's tag from a track.
	 *
	 * @param	string	$artist		The artist name in question. (Required)
	 * @param	string	$track		The track name in question. (Required)
	 * @param	string	$tag		A single user tag to remove from this track. (Required)
	 * @param	Session	$session	A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Required)
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function removeTag($artist, $track, $tag, $session){
		LastFM_Caller_CallerFactory::getDefaultCaller()->signedCall('track.removeTag', array(
			'artist' => $artist,
			'track'  => $track,
			'tag'    => $tag
		), $session, 'POST');
	}

	/** Search for a track by track name. Returns track matches sorted by relevance.
	 *
	 * @param	string	$track	The track name in question. (Required)
	 * @param	string	$artist	Narrow your search by specifying an artist. (Optional)
	 * @param	integer	$limit	Limit the number of tracks returned at one time. Default (maximum) is 30. (Optional)
	 * @param	integer	$page	Scan into the results by specifying a page number. Defaults to first page. (Optional)
	 * @return	PaginatedResult	A PaginatedResult object.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function search($track, $artist = null, $limit = null, $page = null){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('track.search', array(
			'artist' => $artist,
			'track'  => $track,
			'limit'  => $limit,
			'page'   => $page
		));

		$tracks = array();

		foreach($xml->trackmatches->children() as $track){
			$tracks[] = LastFM_Track::fromSimpleXMLElement($track);
		}

		$opensearch = $xml->children('http://a9.com/-/spec/opensearch/1.1/');

		return new LastFM_PaginatedResult(
			LastFM_Util::toInteger($opensearch->totalResults),
			LastFM_Util::toInteger($opensearch->startIndex),
			LastFM_Util::toInteger($opensearch->itemsPerPage),
			$tracks
		);
	}

	/** Share a track twith one or more last.fm users or other friends.
	 *
	 * @param	string	$artist		An artist name. (Required)
	 * @param	string	$track		A track name. (Required)
	 * @param	array	$recipients	A comma delimited list of email addresses or last.fm usernames. Maximum is 10. (Required)
	 * @param	string	$message	An optional message to send with the recommendation. If not supplied a default message will be used. (Optional)
	 * @param	Session	$session	A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Required)
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function share($artist, $track, array $recipients,
								 $message = null, $session){
		LastFM_Caller_CallerFactory::getDefaultCaller()->signedCall('track.share', array(
			'artist'    => $artist,
			'track'     => $track,
			'recipient' => implode(',', $recipients),
			'message'   => $message
		), $session, 'POST');
	}

	/** Get a track playlist for streaming. INOFFICIAL.
	 *
	 * @param	string	$artist	An artist name. (Required)
	 * @param	string	$track	A track name. (Required)
	 * @return	mixed			A Playlist object.
	 *
	 * @static
	 * @access	public
	 * @throws	Error
	 */
	public static function getPlaylist($artist, $track){
		$xml = LastFM_Caller_CallerFactory::getDefaultCaller()->call('track.getPlayerMenu', array(
			'artist' => $artist,
			'track'  => $track
		));

		return LastFM_Playlist::fetch(LastFM_Util::toString($xml->playlist->url), true, true);
	}

	/** Create a Track object from a SimpleXMLElement.
	 *
	 * @param	SimpleXMLElement	$xml	A SimpleXMLElement.
	 * @return	Track						A Track object.
	 *
	 * @static
	 * @access	public
	 * @internal
	 */
	public static function fromSimpleXMLElement(SimpleXMLElement $xml){
		$images  = array();
		$topTags = array();

		if(count($xml->image) > 1){
			foreach($xml->image as $image){
				$images[LastFM_Util::toImageType($image['size'])] = LastFM_Util::toString($image);
			}
		}
		else{
			$images[LastFM_Media::IMAGE_UNKNOWN] = LastFM_Util::toString($xml->image);
		}

		if($xml->toptags){
			foreach($xml->toptags->children() as $tag){
				$topTags[] = LastFM_Tag::fromSimpleXMLElement($tag);
			}
		}

		if($xml->artist){
			if($xml->artist->name && $xml->artist->mbid && $xml->artist->url){
				$artist = new LastFM_Artist(
					LastFM_Util::toString($xml->artist->name),
					LastFM_Util::toString($xml->artist->mbid),
					LastFM_Util::toString($xml->artist->url),
					array(), 0, 0, 0, array(), array(), '', 0.0
				);
			}
			else{
				$artist = LastFM_Util::toString($xml->artist);
			}
		}
		else if($xml->creator){
			$artist = LastFM_Util::toString($xml->creator);
		}
		else{
			$artist = '';
		}
        
        
        //$artist, $name, $id, $mbid, $url, array $images, $listeners, $playCount, $releaseDate, array $topTags, $wiki
        if($xml->album) {
            if($xml->album->artist && $xml->album->title) {
                $album = new LastFM_Album($xml->album->artist, $xml->album->title, -1, $xml->album->mbid, $xml->album->url, array(), -1, -1, -1, array(), '');
            } else {
                $album = LastFM_Util::toString($xml->album);
            }
        } else {
            $album = '';
        }

		if($xml->name){
			$name = LastFM_Util::toString($xml->name);
		}
		else if($xml->title){
			$name = LastFM_Util::toString($xml->title);
		}
		else{
			$name = '';
		}

		// TODO: <extension application="http://www.last.fm">

		return new LastFM_Track(
			$artist,
			$album,
			$name,
			LastFM_Util::toString($xml->mbid),
			LastFM_Util::toString($xml->url),
			$images,
			LastFM_Util::toInteger($xml->listeners),
			LastFM_Util::toInteger($xml->playcount),
			LastFM_Util::toInteger($xml->duration),
			$topTags,
			LastFM_Util::toInteger($xml->id),
			LastFM_Util::toString($xml->location),
			LastFM_Util::toBoolean($xml->streamable),
			LastFM_Util::toBoolean($xml->streamable['fulltrack']),
			$xml->wiki, // TODO: Wiki object
			LastFM_Util::toTimestamp($xml->date)
		);
	}
}



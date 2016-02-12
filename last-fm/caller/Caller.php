<?php

/** Calls API methods using REST requests.
 *
 * @package	php-lastfm-api
 * @author  Felix Bruns <felixbruns@web.de>
 * @version	1.0
 */
abstract class LastFM_Caller {
	/** A Cache instance
	 *
	 * @var LastFM_Cache
	 * @access	protected
	 */
	protected $cache;

	/** Last.fm API key
	 *
	 * @var string
	 * @access	protected
	 */
	protected $apiKey;

	/** Last.fm API secret
	 *
	 * @var string
	 * @access	protected
	 */
	protected $apiSecret;

	/** Last.fm API base URL
	 *
	 * @var string
	 * @access	public
	 */
	const API_URL = 'http://ws.audioscrobbler.com/2.0/';
    
    /** Last.fm API requests per minute limit
	 *
	 * @var int
	 * @access	public
	 */
	const API_RATE_LIMIT = 300;
    
    /** Requests per minute limit
	 *
	 * @var int
	 * @access	protected
	 */
    protected $rateLimit = 0;
    
	/** Get a Caller instance.
	 *
	 * @return	Caller	A Caller instance.
	 * @static
	 * @access	public
	 */
	public static function getInstance() {}

	/** Call an API method.
	 *
	 * @param	string	$method			API method to call. (Required)
	 * @param	array	$params			Request parameters to send. (Optional)
	 * @param	string	$requestMethod	Request-method for calling (defaults to 'GET'). (Optional)
	 * @return	SimpleXMLElement		A SimpleXMLElement object.
	 *
	 * @access	public
	 */
	public function call($method, array $params = array(), $requestMethod = 'GET'){
		/* Set call parameters */
		$callParams = array(
			'method'  => $method,
			'api_key' => $this->apiKey
		);

		/* Add call parameters to other request parameters */
		$params = array_merge($callParams, $params);
		$params = LastFM_Util::toUTF8($params);
        
		/* Call API */
		return $this->internalCall($params, $requestMethod);
	}

	/** Call an API method which needs to be signed.
	 *
	 * @param	string	$method			API method to call. (Required)
	 * @param	array	$params			Request parameters to send. (Optional)
	 * @param	Session	$session		A session obtained by {@link de.felixbruns.lastfm.Auth#getSession Auth::getSession} or {@link de.felixbruns.lastfm.Auth#getMobileSession Auth::getMobileSession}. (Optional)
	 * @param	string	$requestMethod	Request-method for calling (defaults to 'GET'). (Optional)
	 * @return	SimpleXMLElement		A SimpleXMLElement object.
	 *
	 * @access	public
	 */
	public function signedCall($method, array $params = array(), $session = null,
							   $requestMethod = 'GET'){
		/* Set call parameters */
		$callParams = array(
			'method'  => $method,
			'api_key' => $this->apiKey
		);

		/* If session is set, add session key */
		if($session != null){
			$callParams['sk'] = $session->getKey();
		}

		/* Add call parameters to other request parameters */
		$params = array_merge($callParams, $params);
		$params = LastFM_Util::toUTF8($params);

		/* Add API signature */
		$params['api_sig'] = LastFM_Auth::getApiSignature($params, $this->apiSecret);

		/* Call API */
		return $this->internalCall($params, $requestMethod);
	}

    
    public function isRateLimited() {
        
        if($this->rateLimit <= 0)
            return false;
        
        $minute = 60;
        
        if($this->calculateNewMinuteThottle() > $minute)
            return true;
        
        return false;
    }
    
    private function calculateNewMinuteThottle() {
        
        $minute = 60;
        $throttleInfo = $this->getThrottleInfo();
        
        if(empty($throttleInfo)) {
            $throttleInfo = new stdClass;
            $throttleInfo->minuteThrottle = null;
            $throttleInfo->lastApiRequest = time();
        }
        
        $lastApiRequest = $throttleInfo->lastApiRequest; # get from the cache; in epoch seconds
        $lastApiDiff = time() - $lastApiRequest; # in seconds
        $minuteThrottle = $throttleInfo->minuteThrottle; # get from the cache
        
        if(is_null($minuteThrottle)){
            $newMinuteThrottle = 0;
        }
        else{
            $newMinuteThrottle = $minuteThrottle - $lastApiDiff;
            $newMinuteThrottle = $newMinuteThrottle < 0 ? 0 : $newMinuteThrottle;
            $newMinuteThrottle += $minute / $this->rateLimit;
        }
        
        return $newMinuteThrottle;
    }
    
    public function updateRateLimit() {
        $directory = APPPATH.'cache/lastfm.cache';
        
        $data = new stdClass;
        $data->minuteThrottle = $this->calculateNewMinuteThottle();
        $data->lastApiRequest = time();
        
        file_put_contents($directory.'/throttleInfo.json', json_encode($data));
    }
    
    public function getThrottleInfo() {
        $directory = APPPATH.'cache/lastfm.cache';
        
        $data = @file_get_contents($directory.'/throttleInfo.json');
                
        if($data === false)
            return false;
        else
            return json_decode($data);
    }
    
    
	/** Send a query using a specified request-method.
	 *
	 * @param	string	$query			Query to send. (Required)
	 * @param	string	$requestMethod	Request-method for calling (defaults to 'GET'). (Optional)
	 * @return	SimpleXMLElement		A SimpleXMLElement object.
	 *
	 * @access	protected
	 * @internal
	 */
	protected abstract function internalCall($params, $requestMethod = 'GET');

	/** Set the last.fm API key to be used.
	 *
	 * @param	string	$apiKey	A last.fm API key. (Required)
	 * @access	public
	 */
	public function setApiKey($apiKey){
		$this->apiKey = $apiKey;
	}

	/** Get the last.fm API key which is used.
	 *
	 * @return	string	A last.fm API key.
	 * @access	public
	 */
	public function getApiKey(){
		return $this->apiKey;
	}

	/** Set the last.fm API secret to be used.
	 *
	 * @param	string	$apiSecret	A last.fm API secret. (Required)
	 * @access	public
	 */
	public function setApiSecret($apiSecret){
		$this->apiSecret = $apiSecret;
	}
    
    /** Set the rate limit to be used.
	 *
	 * @param	int	$rateLimit	Rate limit setting.
	 * @access	public
	 */
	public function setRateLimit($rateLimit){
        if($rateLimit > self::API_RATE_LIMIT) {
            throw new Exception('Specified rateLimit exceeds API rate limit of '.self::API_RATE_LIMIT.' requests per minute.');
        }
        
		$this->rateLimit = (int)$rateLimit;
	}

	/** Get the last.fm API secret which is used.
	 *
	 * @return	string	A last.fm API secret.
	 * @access	public
	 */
	public function getApiSecret(){
		return $this->apiSecret;
	}

	/** Sets the active {@link Cache} (null to disable caching).
	 *
	 * @param	Cache	$cache	A Cache object. (Required)
	 * @access	public
	 */
	public function setCache($cache){
		$this->cache = $cache;
	}

	/** Get the current {@link Cache}.
	 *
	 * @return	Cache	A Cache object.
	 * @access	public
	 */
	public function getCache(){
		return $this->cache;
	}
    
    /** Get the rate limit
	 *
	 * @return	int	The current rate limit.
	 * @access	public
	 */
	public function getRateLimit(){
		return $this->rateLimit;
	}
}
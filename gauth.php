<?php

    function service_account(&$client, $useremail = 'admin-moogle@luther.edu', $service = 'https://www.googleapis.com/auth/drive', $client_id = '1016393342084-dqql4goj9s0l402sbf4dtnoq2tsk0hp8.apps.googleusercontent.com', $service_account_name = '1016393342084-dqql4goj9s0l402sbf4dtnoq2tsk0hp8@developer.gserviceaccount.com') {
        global $CFG;
        include_once "$CFG->dirroot/google/google-api-php-client/examples/templates/base.php";

        require_once "$CFG->dirroot/google/google-api-php-client/autoload.php";

        //$key_file_location = "$CFG->dirroot/google/key.p12"; //key.p12
	   $key_file_location = $CFG->dirroot.'/google/key.p12';
        if ($client_id == '<YOUR_CLIENT_ID>'
            || !strlen($service_account_name)
            || !strlen($key_file_location)) {
          echo missingServiceAccountDetailsWarning();
        }

        $client->setApplicationName("Service_account");

        if (isset($_SESSION['service_token'])) {
            unset($_SESSION['service_token']);
//          $client->setAccessToken($_SESSION['service_token']);
        }
//        $client->getAuth()->revokeToken($client->auth->token);
        $key = file_get_contents($key_file_location);
        $cred = new Google_Auth_AssertionCredentials(
            $service_account_name,
            array($service),
            $key,
            'notasecret',
            'http://oauth.net/grant_type/jwt/1.0/bearer',
            $useremail
        );
        $client->setAssertionCredentials($cred);
        if ($client->getAuth()->isAccessTokenExpired()) {
          $client->getAuth()->refreshTokenWithAssertion($cred);
        }
        $_SESSION['service_token'] = $client->getAccessToken();
        return json_decode($client->getAccessToken());

    }
    // still used?    
    function caloauth($http_method, $basefeed, $accesstoken, $postdata = null) {
            $authheader = "Authorization: OAuth " . $accesstoken;
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $basefeed);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($http_method == 'POST') {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array($authheader, 'Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            } else { // delete
                curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header, 'Content-Type: application/json'));
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
            }

            $jsonreturn = curl_exec($ch);

            return json_decode($jsonreturn);
        } // ends else from <all authenticated>
//    }
    /**
   * Uses two-legged OAuth to respond to a Google documents list API request
   * @param string $base_feed Full URL of the resource to access
   * @param array $params (optional) parameters to be added to url line
   * @param string $type The HTTP method (GET, POST, PUT, DELETE)
   * @param string $postData (optional) POST/PUT request body
   * @param string $version (optional) if not sent will be set to 3.0
   * @param string $content_type (optional) what kind of content is being sent
   * @param string $slug (optional) used in determining the revision of a document
   * @param boolean $batch is this a batch transmission?
   * @return string $response body from the server
   */
    function twolegged($base_feed, $params, $type, $postdata = null, $version = null, $content_type = null, $slug=null, $batch=null) {
        global $CFG;
        require_once($CFG->dirroot . '/repository/morsle/lib.php'); // for morsle_decode
        require_once($CFG->dirroot . '/google/oauth.php');
        // Establish an OAuth consumer based on our admin 'credentials'

        if ( !$CONSUMER_KEY = get_config('morsle','consumer_key')) {
            return NULL;
        }

        if( !$CONSUMER_SECRET = get_config('morsle','oauthsecretstr') ) {
            return NULL;
        }

        $CONSUMER_SECRET = morsle_decode($CONSUMER_SECRET);
        $consumer = new OAuthConsumer($CONSUMER_KEY, $CONSUMER_SECRET, NULL);
        // Create an Atom entry
        $contactAtom = new DOMDocument();
    //    $contactAtom = null;
        $request = OAuthRequest::from_consumer_and_token($consumer, NULL, $type, $base_feed, $params);
        // Sign the constructed OAuth request using HMAC-SHA1
        $request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
        //  scope=https://docs.google.com/feeds/%20http://spreadsheets.google.com/feeds/%20https://docs.googleusercontent.com/
        // Make signed OAuth request to the Contacts API server
            if (!is_null($params)) {
                $url = $base_feed . '?' . implode_assoc('=', '&', $params);
            } else {
                    $url = $base_feed;
            }
        $header_request = $request->to_header();
        $response = send_request($request->get_normalized_http_method(), $url, $header_request, $contactAtom, $postdata, $version, $content_type, $slug, $batch);
        return $response;
    }


   /**
   * Makes an HTTP request to the specified URL
   * @param string $http_method The HTTP method (GET, POST, PUT, DELETE)
   * @param string $url Full URL of the resource to access
   * @param string $auth_header (optional) Authorization header
   * @param DOM $contactAtom (optional) DOM document coming from an OAuth setup
   * @param string $postData (optional) POST/PUT request body
   * @param string $version (optional) if not sent will be set to 3.0
   * @param string $content_type (optional) what kind of content is being sent
   * @param string $slug (optional) used in determining the revision of a document
   * @param boolean $batch is this a batch transmission?
   * @return string $returnval body from the server
   */
  function send_request($http_method, $url, $auth_header=null, $contactAtom=null, $postData=null, $version=null, $content_type = null, $slug=null, $batch=null) {
    global $success;
    $returnval = new stdClass();
  	$curl = curl_init($url);
    $version = $version == null ? 'Gdata-Version: 3.0' : 'Gdata-Version: ' . $version;
    if (is_null($content_type)) {
	    $content_type = 'Content-Type: application/atom+xml';
    } else {
	    $content_type = 'Content-Type: ' . $content_type;
    }
	$postarray = array($content_type, $auth_header, $version);
    // change this to be an array of values
    if(!is_null($postData)) {
        $length = strlen($postData);
        $postarray[] = 'Content-Length: ' . s($length);
    }
    if (!is_null($slug)) {
    	$postarray[] = 'Slug: ' . $slug;
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    switch($http_method) {
      case 'GET':
        if ($auth_header) {
          curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header,
              $version));
        }
        break;
      case 'POST':
		if ($batch !== null) {
			$postarray[] = 'If-Match: *';
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $postarray);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        break;
      case 'PUT':
      	$postarray[] = 'If-Match: *';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $postarray);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        break;
      case 'DELETE':
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($auth_header,
            $version, 'If-Match: *'));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_method);
        break;
    }
    $response = curl_exec($curl);

    // this usually only happens with calendar and calendar events
    if (strpos($response, 'gsessionid') || strpos($response, 'Moved Temporarily')) {
		preg_match("(https://([^\"']+))i",$response,$match);
		$url = $match[0];
	    curl_close($curl);
		$response = send_request($http_method, $url, $auth_header, $contactAtom, $postData, $version, null, $slug, $batch);
//	    curl_close($curl);
//		$returnval = $response;
//	    return $returnval;
    }
    if (isset($response->info)) { // we're in the second time around and the build of the return value has already occurred
    	return $response;
    } else {
		$info = curl_getinfo($curl);
	    curl_close($curl);
	    if (!is_null($response)) {
			$returnval->response = $response;
			$returnval->info = $info;
	    }
		if ($returnval->info['http_code'] == 200 || $returnval->info['http_code'] == 201) {
			$success = true;
		} else {
			$success = false;
		}
	    return $returnval;
    }
}

/**
   * Joins key:value pairs by inner_glue and each pair together by outer_glue
   * @param string $inner_glue The HTTP method (GET, POST, PUT, DELETE)
   * @param string $outer_glue Full URL of the resource to access
   * @param array $array Associative array of query parameters
   * @return string Urlencoded string of query parameters
   */
  function implode_assoc($inner_glue, $outer_glue, $array) {
    $output = array();
    foreach($array as $key => $item) {
      $output[] = $key . $inner_glue . urlencode($item);
    }
    return implode($outer_glue, $output);
  }

function morsle_encode($value) {
	global $CFG;
	$salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : 'morsle';
	$ret = base64_encode(rc4decrypt($value, $salt));
	return $ret;
}

function morsle_decode($value) {
	global $CFG;
	$salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : 'morsle';
	$ret = trim(rc4decrypt(base64_decode($value),$salt));
	return $ret;
}

?>

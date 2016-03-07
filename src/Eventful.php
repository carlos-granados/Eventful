<?php

namespace BlueBayTravel\Eventful;

use SimpleXMLElement;
use tmhOAuth;

class Eventful
{
    /**
     * URI of the REST API
     *
     * @access  public
     * @var     string
     */
    public $api_root;
    public $req_url = 'http://eventful.com/oauth/request_token';
    public $authurl = 'http://eventful.com/oauth/authorize';
    public $acc_url = 'http://eventful.com/oauth/access_token';

    public $using_oauth = 0;
    public $conskey ;
    public $conssec ;
    public $oauth_token;
    public $oauth_token_secret;

    /**
     * Application key (as provided by http://api.eventful.com)
     *
     * @access  public
     * @var     string
     */
    public $app_key   = null;

    /**
     * Latest request URI
     *
     * @access  private
     * @var     string
     */
    private $_request_uri = null;

    /**
     * Latest response as unserialized data
     *
     * @access  public
     * @var     string
     */
    public $_response_data = null;

    /**
     * Create a new client
     *
     * @access  public
     * @param   string      $app_key
     * @param   string      $api_url
     */
    function __construct($app_key, $api_url = 'http://api.eventful.com' )
    {
        $this->app_key  = $app_key;
        $this->api_root = $api_url;
    }

    /**
     * Setup OAuth so we can pass correct OAuth headers
     *
     * @access  public
     * @param   string      $conskey
     * @param   string      $conssec
     * @param   string      $oauth_token
     * @param   string      $oauth_secret
     * @return boolean
     */
    function setup_Oauth($conskey, $conssec, $oauth_token, $oauth_secret )
    {
        $this->conskey     = $conskey;
        $this->conssec     = $conssec;
        $this->oauth_token = $oauth_token;
        $this->oauth_token_secret = $oauth_secret;
        $this->using_oauth = 1;
        return 1;
    }

    /**
     * Call a method on the Eventful API.
     *
     * @access  public
     * @param   string      $method
     * @param   array       $args
     * @param   string      $type
     * @return SimpleXMLElement
     */
    function call($method, $args=array(), $type='rest')
    {
        /* Methods may or may not have a leading slash.  */
        $method = trim($method,'/ ');

        /* Construct the URL that corresponds to the method.  */
        $url = $this->api_root . '/' . $type  . '/' . $method;
        $this->_request_uri = $url;

        // Handle the OAuth request.
        if ($this->using_oauth ) {
            //create a new Oauth request.  By default this uses the HTTP AUTHORIZATION headers and HMACSHA1 signature
            $config = array(
                'consumer_key'    => $this->conskey,
                'consumer_secret' => $this->conssec,
                'token'           => $this->oauth_token,
                'secret'          => $this->oauth_token_secret,
                'method'          => 'POST',
                'use_ssl'         => false,
                'user_agent'      => 'Eventful_PHP_API');
            $tmhOAuth = new tmhOauth($config);
            $multipart = false;
            $app_key_name = 'app_key';
            foreach ($args as $key => $value) {
                if ( preg_match('/_file$/', $key) ) {  // Check for file_upload
                    $multipart = true;
                    $app_key_name = 'oauth_app_key';  // Have to store the app_key in oauth_app_key so it gets sent over in the Authorization header
                }
            }

            $tmhOAuth->user_request(array(
                'method' => 'POST',
                'url' => $tmhOAuth->url($url,''),
                'params' => array_merge( array($app_key_name => $this->app_key), $args),
                'multipart' => $multipart));

            $resp = $tmhOAuth->response['response'];
            $this->_response_data = $resp;
            if ($type ===  "json") {
                $data = json_decode($resp, true);
                if ($data['error'] > 0) {
                    return 'Invalid status : ' . $data['status']  . ' (' . $data['description'] . ')';
                }
            } else {
                $data = new SimpleXMLElement($resp);
                if ($data->getName() === 'error') {
                    $error = $data['string'] . ": " . $data->description;
                    return $error;
                }
            }
            return ($data);
        }


        $postArgs = [
            'app_key' => $this->app_key,
        ];

        foreach ($args as $argKey => $argValue) {
            if (is_array($argValue)) {
                foreach ($argValue as $instance) {
                    $postArgs[$argKey] = $instance;
                }
            } else {
                $postArgs[$argKey] = $argValue;
            }
        }

        $fieldsString = '';

        foreach ($postArgs as $argKey => $argValue) {
            $fieldsString .= $argKey.'='.urlencode($argValue).'&';
        }

        $fieldsString = rtrim($fieldsString, '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_request_uri);
        curl_setopt($ch, CURLOPT_POST, count($postArgs));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return data instead of display to std out

        $cResult = curl_exec($ch);
        $this->_response_data = $cResult;

        curl_close($ch);

        $data = new SimpleXMLElement($cResult);

        /* Check for call-specific error messages */
        if ($data->getName() === 'error')
        {
            $error = $data['string'] . ": " . $data->description;
            return $error;
        }

        return($data);
    }
}

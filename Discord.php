<?php

class Discord {

  /**
   * Redirect URI that is registered with your
   * discord application.
   * @var String
   */
  private $redirectUri = false;

  /**
   * Access token that is received from discord.
   * Usually set after the token exchange.
   * @var String
   */
  private $accessToken = false;

  /**
   * The endpoint that the request will be made to.
   * @var String
   */
  private $endpoint = false;

  /**
   * Params that are either turned into a query string,
   * or sent as the post parameters in a request.
   * @var Array
   */
  private $params = false;

  /**
   * Set this to true if you want the method to automatically
   * redirect for methods like authorization or token.
   * @var Boolean
   */
  private $autoRedirect = true;

  /**
   * The data that is returned from the api
   * @var ArrayObject
   */
  public $data = false;

  /**
   * An error variable that can be checked later.
   * @var string
   */
  public $error = false;

  /**
   * URLS for Discord Oauth
   */
  private $urls = [
    'authorize' => 'https://discord.com/api/oauth2/authorize',
    'token' => 'https://discord.com/api/oauth2/token',
    'api' => 'https://discord.com/api/users/@me'
  ];

  /**
   * Set discord library config variables
   */
  public function __construct ($config = []) {

    /**
     * Set the Redirect URI, however, if it is not provided,
     * try to use the current URL.
     * @var String
     */
    if (isset($config['redirectUri']))
      $this->redirectUri = $config['redirectUri'];
    else
      $this->redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . explode('?', $_SERVER['REQUEST_URI'])[0];

    /**
     * Set the access token through the configuration.
     * @var String
     */
    if (isset($config['accessToken']))
      $this->accessToken = $config['accessToken'];

    /**
     * Set the client id
     * @var String
     */
    if (isset($config['clientId']))
      $this->clientId = $config['clientId'];

    /**
     * Set the client secret
     * @var String
     */
    if (isset($config['clientSecret']))
      $this->clientSecret = $config['clientSecret'];

    /**
     * Set auto redirect
     * @var Boolean
     */
    if (isset($config['autoRedirect']))
      $this->autoRedirect = $config['autoRedirect'];
  }

  /**
   * Set the endpoint
   * @param  string $endpoint
   * @return instance
   */
  public function endpoint ($endpoint) {
    $this->endpoint = $endpoint;
    return $this;
  }

  /**
   * Set the params
   * @param  mixed params
   * @param  array include
   * @return instance
   */
  public function params ($params = false, $include = []) {
    $this->params = $params;

    foreach ($include as $i) {
      if ($i === 'redirect_uri') $this->params['redirect_uri'] = $this->redirectUri;
      if ($i === 'client_id') $this->params['client_id'] = $this->clientId;
      if ($i === 'client_secret') $this->params['client_secret'] = $this->clientSecret;
    }

    return $this;
  }

  /**
   * Set the endpoint
   * @param  string $key
   * @param  mixed  $value
   * @return instance
   */
  public function set ($key, $value) {
    $this->{$key} = $value;
    return $this;
  }

  /**
   * Login process to automatically go through all
   * of the steps.
   * @return mixed
   */
  public function login () {
    $code  = $this->get('code');
    $token = $this->get('token');

    // If no code or token, redirect to the auth page.
    if (!$code && !$token) {
      return $this->authorization();
    } else {

      // If there is a code, exchange for a token.
      if ($code) return $this->token($code);

      // If there is a token, get user data.
      if ($token) {

        // Set accessToken
        $this->set('accessToken', $token);

        // Get the data.
        $this->me();

        return $this->data;
      }
    }
  }

  /**
   * Authorization through discord
   * @param  mixed $scope - optional
   */
  public function authorization ($scope = false) {

    // Set the end point
    $this->endpoint = $this->urls['authorize'];

    // Set the parameters
    $this->params([
      'response_type' => 'code',
      'scope' => ($scope ? $scoe : 'identify guilds email')
    ], [ 'client_id', 'redirect_uri' ]);

    // Get the built URL
    $url = $this->url();

    // If auto redirect, send to the authorization page.
    if ($this->autoRedirect) {
      return header('Location: ' . $url);
    }

    // Return the URL if auto redirect is off.
    return $url;
  }

  /**
   * Takes a code, then exchanges it for an auth code.
   * @param String $code
   */
  public function token ($code = false) {

    // If no code is provided, return false.
    if (!$code) return false;

    // Set the endpoint
    $this->endpoint = $this->urls['token'];

    // Set the params
    $this->params([
      'grant_type' => 'authorization_code',
      'code' => $code
    ], [ 'client_id', 'client_secret', 'redirect_uri' ]);

    // Request the API and get the response.
    $res = $this->request();

    // Validate the response.
    if (!isset($res->access_token)) {
      $this->error = 'No access token was found in token exchange.';
      return false;
    }

    // Build the new URL
    $url = $this->redirectUri . '?token=' . $res->access_token;

    // If auto redirect, send to the authorization page.
    if ($this->autoRedirect) return header('Location: ' . $url);

    // Return the URL if auto redirect is off.
    return $url;
  }

  /**
   * Retrieves data for the current authed user.
   * @return ArrayObject
   */
  public function me () {

    // Do the request
    $user = $this
      ->endpoint($this->urls['api'])
      ->request();

    // Set the data class variable
    $this->data = $user;

    if (!$this->data) {

      $this->error = 'No data returned in @me response';
      return false;
    }

    return true;
  }

  /**
   * Returns the endpoint from the urls class array
   * @return String
   */
  public function url () {
    if (!$this->endpoint) return false;
    if (!$this->params) return $this->endpoint;
    $res = $this->endpoint . '?' . http_build_query($this->params);
    $this->reset();
    return $res;
  }

  /**
   * Handles the CURL request
   * @param  array  $headers (optional)
   * @return mixed
   */
  public function request ($headers = []) {
    if (!$this->endpoint) return false;

    // Format the URL
    $url = $this->endpoint;

    // Init curl for the formatted URL
    $ch = curl_init($url);

    print_r($this->params);

    // Set curl opts
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    // Execute curl
    $response = curl_exec($ch);

    // Pass post vars
    if ($this->params !== false)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->params));

    // Set JSON header
    $headers[] = 'Accept: application/json';

    // If there is an access token, unclude it in headers.
    if($this->accessToken)
      $headers[] = 'Authorization: Bearer ' . $this->accessToken;

    // Pass the headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $res = false;

    try {
      $res = json_decode($response);
    } catch (\Exception $e) {
      return $res;
    }

    $this->reset();

    return $res;
  }

  /**
   * Resets the endpoint and params for
   * future use.
   */
  private function reset () {
    $this->endpoint = false;
    $this->params   = false;
  }

  // Retrieve get variables
  public function get($key = null) {
    if (is_null($key)) return $_GET;
    if (isset($_GET[$key])) {
      return $_GET[$key];
    } else {
      return false;
    }
  }

  // Retrieve POST variables
  public function post($key = null) {
    if (is_null($key)) return $_POST;
    if (isset($_POST[$key])) {
      return $_POST[$key];
    } else {
      return false;
    }
  }
}

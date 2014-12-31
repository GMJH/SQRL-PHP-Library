<?php
/**
 * SQRL
 *
 * Copyright (c)
 *
 * Description
 *
 * Licence
 *
 * @package    SQRL
 * @author     Jürgen Haas <juergen@paragon-es.de>
 * @author     Gary Marriott <ramriot@gmail.com>
 * @copyright  ...
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       https://bitbucket.org/jurgenhaas/sqrl-php
 */

namespace JurgenhaasRamriot\SQRL;

/**
 * This class defines the main structure for SQRL nut building,
 * encoding, decoding and validation.
 */
abstract class Nut extends Common implements Nut_API {

  const PATH_PREFIX = 'sqrl/';
  const NUT_LIFETIME = 600;
  //selector constants
  const SELECT_COOKIE = 'cookie';
  const SELECT_URL = 'url';
  //status constants
  const STATUS_VALID = 'validated';
  const STATUS_INVALID = 'invalid';
  const STATUS_BUILT = 'built';
  const STATUS_FETCH = 'fetched';
  const STATUS_DECODED = 'decoded';
  const STATUS_NOCOOKIE = 'nocookie';
  //internal variables
  protected $operation = 'login';//type of operation of this nut
  protected $params = array();//other parameters for storage in cache
  protected $base_url;
  protected $url;
  protected $scheme;

  protected $raw = array();//raw nut parameters assoc key url/cookie
  protected $encoded = array();//encoded nut strings assoc key url/cookie
  protected $nut = array();//encrypted nut strings assoc key url/cookie
  protected $error = FALSE;//Error flag
  protected $errorCode = 0;//Error code
  protected $msg = array();//Error messages
  protected $clean = TRUE;//Clean object flag
  protected $status = '';//Current object status string

  public function __construct() {
    $this->base_url = $this->build_base_url();
    $this->scheme = $this->use_secure_connection() ? 'sqrl' : 'qrl';
  }

  public function use_secure_connection() {
    return TRUE;
  }

  public function get_connection_port() {
    return 443;
  }

  protected function nut_key($key) {
    if ($key !== self::SELECT_COOKIE && $key !== self::SELECT_URL) {
      throw new NutException('Cookie / Nut selector not passing correct key');
    }
    return $this;
  }

  /**
   * **** All these abstract methods must be present in child classes
   */

  /**
   * required override
   * abstract method to return an encrypted string
   * @param $data
   * @param $part
   * @return
   */
  abstract protected function encrypt($data, $part);

  /**
   * required override
   * abstract method to return an decrypted string
   * @param $data String encrypted string
   * @param $part String either (parent::SELECT_URL or parent::SELECT_COOKIE)
   */
  abstract protected function decrypt($data, $part);

  /**
   * required override
   * abstract method to return the base url of the site
   */
  abstract protected function build_base_url();

  public function get_base_url() {
    return $this->base_url;
  }

  /**
   * required override
   * abstract method to set a persistent cache
   */
  abstract protected function cache_set();

  /**
   * required override
   * abstract method to get a named cache item
   */
  abstract protected function cache_get();

  /**
   * required override
   * abstract method to implement named 32 bit wrapping
   * counters.
   * @param $name String: machine readable string =<255
   *  characters long ot act as counter key
   * @return Int: new count value
   */
  abstract protected function get_named_counter($name);

  /**
   * Build and return the url for this nut.
   *
   * @return string
   */
  function get_nut_url() {
    if (empty($this->url)) {
      $base_url = $this->base_url;
      $requires_leading_slash = TRUE;
      if (strpos($base_url, '/')) {
        // If the base_url contains a path component, then we have to append a
        // single "|" and avoid the subsequent "/" to indicate the domain string.
        $base_url .= '|';
        $requires_leading_slash = FALSE;
      }
      $this->url = $this->scheme . '://' . $base_url . $this->get_path('', TRUE, FALSE, $requires_leading_slash);
    }
    return $this->url;
  }

  /**
   * TBD.
   *
   * @param string $path
   * @param bool $include_nut
   * @param bool $include_base_path
   * @param bool $requires_leading_slash
   * @return string
   */
  function get_path($path, $include_nut = TRUE, $include_base_path = TRUE, $requires_leading_slash = TRUE) {
    $prefix = $include_base_path ? $this->base_path() : '/';
    if (!$requires_leading_slash) {
      $prefix = substr($prefix, 1);
    }
    $suffix = array();
    if ($include_nut) {
      $suffix[] = 'nut=' . $this->get_encrypted_nut(self::SELECT_URL);
    }
    if (defined('SQRL_XDEBUG')) {
      $suffix[] = 'XDEBUG_SESSION_START=IDEA';
    }
    $suffix = empty($suffix) ? '' : '?' . implode('&', $suffix);
    return $prefix . $this::PATH_PREFIX . $path . $suffix;
  }

  private function base_path() {
    // TODO: Return the section after the domain.
    return '/';
  }

  /**
   * Build the nuts
   */
  private function build_nuts() {
    if ($this->status == self::STATUS_BUILT) {
      return;
    }
    //this operation to be done against a clean object only
    try {
      $this->is_clean();
      $this->clean = FALSE;
      $this->source_raw_nuts();
      $this->encode_raw_nuts();
      $this->encrypt_wrapper();
      $this->cache_set();
      $this->set_cookie();
      $this->status = self::STATUS_BUILT;
    }
    catch (NutException $e) {
      //TBD use the generated code as passback and trigger
      $this->errorCode = $e->getCode();
      $this->msg = $e->getMessage();
    }
    //Don't catch standard exceptions
    /*
    catch (Exception $e) {
      echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
    }
    */
    //If PHP >=5.3 a finally clause can be added here to catch rest for now we let it drop through
  }

  /**
   * Get nut from url and cookie parameters
   * @param $cookie_expected Boolean
   * @return $this
   */
  public function fetch_nuts($cookie_expected = FALSE) {
    //this operation to be done against a clean object only
    try {
      $this->is_clean();
      $this->clean = FALSE;
      $this->status = self::STATUS_FETCH;
      //set value for nut in url
      $this->nut[self::SELECT_URL] = $this->fetch_url_nut();
      if ($cookie_expected) {
        $this->nut[self::SELECT_COOKIE] = $this->fetch_cookie_nut();
      }
      $this->decrypt_wrapper($cookie_expected);
      $this->decode_encoded_nuts($cookie_expected);
      $this->cache_get();
      $this->status = self::STATUS_DECODED;
    } catch (NutException $e)//exceptions we throw
    {
      //TBD use the generated code as passback and trigger
      $this->errorCode = $e->getCode();
      $this->msg = $e->getMessage();
    }
    /*
    catch(Exception $e)//exceptions php throws
    {
        echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
    }
    */
    //If PHP >=5.3 a finally clause can be added here to catch rest for now we let it drop through
    return $this;
  }

  public function is_valid_nuts($cookie_expected = FALSE) {
    try {
      $this->is_dirty();
      //check status
      $this->is_nut_status(self::STATUS_DECODED);
      //check for expired nut in url
      $this->is_raw_nut_expired(self::SELECT_URL);
      //if cookie expected
      if ($cookie_expected) {
        $this->is_raw_nut_expired(self::SELECT_COOKIE);
        $this->is_match_encoded_nuts();
      }
    } catch (NutException $e)//exceptions we throw
    {
      //TBD use the generated code as passback and trigger
      $this->errorCode = $e->getCode();
      $this->msg = $e->getMessage();
    }
    /*
    catch(Exception $e)//exceptions php throws
    {
        echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
    }
    */
    //If PHP >=5.3 a finally clause can be added here to catch rest for now we let it drop through
    return $this;
  }

  /**
   * Return encrypted nut
   * @param $key
   *  constant for selection
   * @return string
   *  URL safe String
   */
  public function get_encrypted_nut($key) {
    $this->nut_key($key);
    $this->build_nuts();
    return $this->nut[$key];
  }

  /**
   * Return encoded nut / normally both url and cookie the same
   * @param $key
   *  constant for selection
   * @return string
   *  Byte String
   */
  public function get_encoded_nut($key) {
    $this->nut_key($key);
    $this->build_nuts();
    return $this->encoded[$key];
  }

  /**
   * Return raw nut array
   * @param $key
   *  constant for selection
   * @return array
   */
  public function get_raw_nut($key) {
    $this->nut_key($key);
    $this->build_nuts();
    return $this->raw[$key];
  }

  /**
   * Return status property
   */
  public function get_status() {
    return $this->status;
  }

  /**
   * Return error messages string
   */
  public function get_msg() {
    return $this->msg;
  }

  /**
   * Return error messages code
   */
  public function get_errorCode() {
    return $this->errorCode;
  }

  public function is_exception() {
    return $this->errorCode ? TRUE : FALSE;
  }

  private function source_raw_nuts() {
    $this->raw[self::SELECT_URL] = array(
      'time' => $_SERVER['REQUEST_TIME'],
      'ip' => $this->_get_ip_address(),
      'counter' => $this->get_named_counter('sqrl_nut'),
      'random' => $this->_get_random_bytes(4),
    );
    //clone raw url data to raw cookie
    $this->raw[self::SELECT_COOKIE] = $this->raw[self::SELECT_URL];
    return $this;
  }

  /**
   * encode raw nut array into a byte string
   */
  private function encode_raw_nuts() {
    $keys = array(self::SELECT_COOKIE, self::SELECT_URL);
    foreach ($keys as $key) {
      $ref = &$this->raw[$key];
      //format bytes
      $output = pack('LLL', $ref['time'], $this->_ip_to_long($ref['ip']), $ref['counter']) . $ref['random'];
      $this->encoded[$key] = $output;
    }
    return $this;
  }

  /**
   * decode encoded byte string into array parts
   * @param $cookie_expected
   * @return $this
   * @throws NutException
   */
  private function decode_encoded_nuts($cookie_expected) {
    $keys = array(self::SELECT_URL);
    if ($cookie_expected) {
      $keys[] = self::SELECT_COOKIE;
    }
    foreach ($keys as $key) {
      $ref = &$this->encoded;
      //test byte string
      if (strlen($ref[$key]) == 16) {
        $this->raw[$key] = array(
          'time' => $this->decode_time($ref[$key]),
          'ip' => $this->decode_ip($ref[$key]),
          'counter' => $this->decode_counter($ref[$key]),
          'random' => $this->decode_random($ref[$key]),
        );
      }
      else {
        throw new NutException(SQRL::get_message()->format('Nut string length incorrect: @len', array('@len' => strlen($ref[$key]))));
      }
    }
    return $this;
  }

  private function decode_time($bytes) {
    $output = unpack('L', $this->_bytes_extract($bytes, 0, 4));
    return array_shift($output);
  }

  private function decode_ip($bytes) {
    $output = unpack('L', $this->_bytes_extract($bytes, 4, 4));
    return $this->_long_to_ip(array_shift($output));
  }

  private function decode_counter($bytes) {
    $output = unpack('L', $this->_bytes_extract($bytes, 8, 4));
    return array_shift($output);
  }

  private function decode_random($bytes) {
    $output = $this->_bytes_extract($bytes, 12, 4);
    return $output;
  }

  private function encrypt_wrapper() {
    $keys = array(self::SELECT_URL, self::SELECT_COOKIE);
    foreach ($keys as $key) {
      $ref = &$this->encoded[$key];
      $data = $this->encrypt($ref, $key);
      $this->nut[$key] = strtr($data, array('+' => '-', '/' => '_', '=' => ''));
    }
    return $this;
  }

  private function decrypt_wrapper() {
    $keys = array(self::SELECT_URL, self::SELECT_COOKIE);
    foreach ($keys as $key) {
      $ref = &$this->nut[$key];
      $data = strtr($ref, array('-' => '+', '_' => '/')) . '==';
      $this->encoded[$key] = $this->decrypt($data, $key);
    }
    return $this;
  }

  private function set_cookie() {
    setcookie('sqrl', $this->nut[self::SELECT_COOKIE], $_SERVER['REQUEST_TIME'] + self::NUT_LIFETIME, '/', $this->get_base_url());
    return $this;
  }

  private function is_nut_status($status) {
    if ($this->status !== $status) {
      throw new NutException(SQRL::get_message()->format('Nut status check failed: @thisStatus != @chkStatus', array(
        '@thisStatus' => $this->status,
        '@chkStatus' => $status
      )));
    }
  }

  private function is_clean() {
    if (!$this->clean) {
      throw new NutException('Clean nut expected dirty nut found');
    }
    return $this;
  }

  private function is_dirty() {
    if ($this->clean) {
      throw new NutException('Dirty nut expected clean nut found');
    }
    return $this;
  }

  private function fetch_url_nut() {
    $nut = isset($_GET['nut']) ? $_GET['nut'] : FALSE;
    if (!$nut) {
      throw new NutException('Nut missing from GET request');
    }
    return $nut;
  }

  private function fetch_cookie_nut() {
    $nut = isset($_COOKIE['sqrl']) ? $_COOKIE['sqrl'] : '';
    if (!$nut) {
      throw new NutException('Nut missing from COOKIE request');
    }
    return $nut;
  }

  private function is_match_raw_nuts() {
    $error = FALSE;
    foreach ($this->raw[self::SELECT_URL] as $key => $value) {
      if ($this->raw[self::SELECT_COOKIE][$key] != $value) {
        $error = TRUE;
        break;
      }
    }
    if ($error) {
      throw new NutException('Nut in url and cookie raw parameter arrays do not match');
    }
    return $this;
  }

  private function is_match_encoded_nuts() {
    $str_url = $this->encoded[self::SELECT_URL];
    $str_cookie = $this->encoded[self::SELECT_COOKIE];
    if (!$this->time_safe_strcomp($str_url, $str_cookie)) {
      throw new NutException('Nut in url and cookie encoded strings do not match');
    }
    return $this;
  }

  private function is_raw_nut_expired($key) {
    $this->nut_key($key);
    $nut = $this->get_raw_nut($key);
    if ($nut['time'] < $_SERVER['REQUEST_TIME']) {
      throw new NutException('Nut time validity expired');
    }
    return $this;
  }

  /**
   * helper function to fetch the client ip address allowing for
   * several possible server network configurations
   *
   * @return string
   *  IPv4 address
   */
  protected function _get_ip_address() {
    foreach (array(
               'HTTP_CLIENT_IP',
               'HTTP_X_FORWARDED_FOR',
               'HTTP_X_FORWARDED',
               'HTTP_X_CLUSTER_CLIENT_IP',
               'HTTP_FORWARDED_FOR',
               'HTTP_FORWARDED',
               'REMOTE_ADDR'
             ) as $key) {
      if (array_key_exists($key, $_SERVER) === TRUE) {
        foreach (explode(',', $_SERVER[$key]) as $ip_address) {
          // Just to be safe
          $ip_address = trim($ip_address);

          if (filter_var($ip_address,
              FILTER_VALIDATE_IP,
              FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== FALSE) {
            return $ip_address;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * function got fetch a series of high entropy pseudo random bytes
   *
   * @param int $count
   *  length in bytes for returned random
   * @return string
   *  binary string of length $count bytes
   */
  protected function _get_random_bytes($count) {
    static $random_state, $bytes, $has_openssl;
    $missing_bytes = $count - strlen($bytes);
    if ($missing_bytes > 0) {
      // PHP versions prior 5.3.4 experienced openssl_random_pseudo_bytes()
      // locking on Windows and rendered it unusable.
      if (!isset($has_openssl)) {
        $has_openssl = version_compare(PHP_VERSION, '5.3.4', '>=') && function_exists('openssl_random_pseudo_bytes');
      }
      // openssl_random_pseudo_bytes() will find entropy in a system-dependent
      // way.
      if ($has_openssl) {
        $bytes .= openssl_random_pseudo_bytes($missing_bytes);
      }
      // Else, read directly from /dev/urandom, which is available on many *nix
      // systems and is considered cryptographically secure.
      elseif ($fh = @fopen('/dev/urandom', 'rb')) {
        // PHP only performs buffered reads, so in reality it will always read
        // at least 4096 bytes. Thus, it costs nothing extra to read and store
        // that much so as to speed any additional invocations.
        $bytes .= fread($fh, max(4096, $missing_bytes));
        fclose($fh);
      }
      // If we couldn't get enough entropy, this simple hash-based PRNG will
      // generate a good set of pseudo-random bytes on any system.
      // Note that it may be important that our $random_state is passed
      // through hash() prior to being rolled into $output, that the two hash()
      // invocations are different, and that the extra input into the first one -
      // the microtime() - is prepended rather than appended. This is to avoid
      // directly leaking $random_state via the $output stream, which could
      // allow for trivial prediction of further "random" numbers.
      if (strlen($bytes) < $count) {
        // Initialize on the first call. The contents of $_SERVER includes a mix of
        // user-specific and system information that varies a little with each page.
        if (!isset($random_state)) {
          $random_state = print_r($_SERVER, TRUE);
          if (function_exists('getmypid')) {
            // Further initialize with the somewhat random PHP process ID.
            $random_state .= getmypid();
          }
          $bytes = '';
        }

        do {
          $random_state = hash('sha256', microtime() . mt_rand() . $random_state);
          $bytes .= hash('sha256', mt_rand() . $random_state, TRUE);
        } while (strlen($bytes) < $count);
      }
    }
    $output = substr($bytes, 0, $count);
    $bytes = substr($bytes, $count);
    return $output;
  }

  /**
   * @param $str1
   * @param $str2
   * @return bool
   */
  protected function time_safe_strcomp($str1, $str2) {
    $str1_len = strlen($str1);
    $str2_len = strlen($str2);
    if ($str1_len == 0 || $str2_len == 0) {
      throw new \InvalidArgumentException('This function cannot safely compare against an empty given string');
    }
    $res = $str1_len ^ $str2_len;
    for ($i = 0; $i < $str1_len; ++$i) {
      $res |= ord($str1[$i % $str1_len]) ^ ord($str2[$i]);
    }
    if ($res === 0) {
      return TRUE;
    }
    return FALSE;
  }

  function get_operation() {
    return $this->operation;
  }

  function set_operation($operation) {
    $this->operation = $operation;
  }

  function get_operation_param($key) {
    return $this->params[$key];
  }

  function get_operation_params($key) {
    return array(
      'op' => $this->get_operation(),
      'params' => $this->params,
    );
  }

  function set_operation_param($key, $value) {
    $this->params[$key] = $value;
  }

}

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
abstract class Nut extends Common {

  const PATH_PREFIX = 'sqrl/';
  const NUT_LIFETIME = 600;

  const SELECT_COOKIE = 'cookie';
  const SELECT_URL = 'url';

  const STATUS_VALID = 'validated';
  const STATUS_INVALID = 'invalid';
  const STATUS_BUILT = 'built';
  const STATUS_FETCH = 'fetched';
  const STATUS_DECODED = 'decoded';
  const STATUS_NOCOOKIE = 'nocookie';

  private $operation = 'login';
  private $params = array();
  private $base_url;
  private $url;
  private $scheme;

  private $raw = array();
  private $encoded = array();
  private $nut = array();
  private $error = FALSE;
  private $error_code = 0;
  private $error_message = array();
  private $clean = TRUE;
  private $status = '';

  public function __construct($fetch, $cookie_expected = FALSE) {
    parent::__construct();
    $this->base_url = strtolower($this->build_base_url());
    $this->scheme = $this->use_secure_connection() ? 'sqrl' : 'qrl';
    if ($fetch) {
      $this->fetch_nuts($cookie_expected);
    }
  }

  abstract protected function build_base_url();
  abstract public    function is_secure_connection_available();
  abstract protected function encrypt($data, $key);
  abstract protected function decrypt($data, $key);
  abstract protected function save();
  abstract protected function load();
  abstract protected function counter();
  abstract protected function authenticate($ids, $pids, $urs);
  abstract protected function authenticated();

  #region Main Final ===========================================================

  final public function validate_nuts($cookie_expected = FALSE) {
    try {
      $this->is_dirty();
      $this->is_nut_status(self::STATUS_DECODED);
      $this->is_raw_nut_expired(self::SELECT_URL);
      if ($cookie_expected) {
        $this->is_raw_nut_expired(self::SELECT_COOKIE);
        $this->is_match_encoded_nuts();
        $this->is_match_raw_nuts();
      }
    }
    catch (NutException $e) {
      $this->error_code = $e->getCode();
      $this->error_message = $e->getMessage();
    }
  }

  final public function is_exception() {
    return $this->error_code ? TRUE : FALSE;
  }

  final public function is_valid() {
    return $this->error_code ? FALSE : TRUE;
  }

  final public function is_authenticated() {
    if ($this->authenticated()) {
      $this->del_cookie();
      return TRUE;
    }
    return FALSE;
  }

  final public function get_base_url() {
    return $this->base_url;
  }

  final public function get_path($path, $include_nut = TRUE, $include_base_path = TRUE, $requires_leading_slash = TRUE) {
    $prefix = $include_base_path ? $this->base_path() : '/';
    if (!$requires_leading_slash) {
      $prefix = substr($prefix, 1);
    }
    $suffix = array();
    if ($include_nut) {
      $suffix[] = 'nut=' . $this->get_public_nut(self::SELECT_URL);
    }
    if (defined('SQRL_XDEBUG')) {
      $suffix[] = 'XDEBUG_SESSION_START=IDEA';
    }
    $suffix = empty($suffix) ? '' : '?' . implode('&', $suffix);
    return $prefix . $this::PATH_PREFIX . $path . $suffix;
  }

  final public function get_nut_url() {
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

  final public function get_encoded_nut($key) {
    $this->nut_key($key);
    $this->build_nuts();
    return $this->encoded[$key];
  }

  final public function get_public_nut($key) {
    $this->nut_key($key);
    $this->build_nuts();
    return $this->nut[$key];
  }

  final public function get_raw_nut($key) {
    $this->nut_key($key);
    $this->build_nuts();
    return $this->raw[$key];
  }

  final public function get_status() {
    return $this->status;
  }

  final public function get_errorCode() {
    return $this->error_code;
  }

  final public function get_error_message() {
    return $this->error_message;
  }

  final public function get_operation() {
    return $this->operation;
  }

  final public function set_operation($operation) {
    $this->operation = $operation;
  }

  final public function get_operation_param($key) {
    return $this->params[$key];
  }

  final public function get_operation_params() {
    return array(
      'op' => $this->get_operation(),
      'params' => $this->params,
    );
  }

  final public function set_operation_param($key, $value) {
    $this->params[$key] = $value;
  }

  final public function set_operation_params($values) {
    foreach ($values as $key => $value) {
      $this->set_operation_param($key, $value);
    }
  }

  #endregion

  #region Main (potential overwrite) ===========================================

  public function use_secure_connection() {
    return $this->is_secure_connection_available();
  }

  public function get_connection_port() {
    return $this->is_secure_connection_available() ? 443 : 80;
  }

  #endregion

  #region Internal =============================================================

  private function base_path() {
    // TODO: Return the section after the domain.
    return '/';
  }

  private function set_cookie() {
    setcookie('sqrl', $this->nut[self::SELECT_COOKIE], $this->request_time + self::NUT_LIFETIME, '/', $this->get_base_url());
  }

  private function del_cookie() {
    $params = session_get_cookie_params();
    setcookie('sqrl', '', $this->request_time - 3600, $params['path'], $params['domain']);
    unset($_COOKIE['sqrl']);
  }

  private function _get_ip_address() {
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

  private function _get_random_bytes($count) {
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

  private function time_safe_strcomp($str1, $str2) {
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

  #endregion

  #region Internal nut handling ================================================

  private function nut_key($key) {
    if ($key !== self::SELECT_COOKIE && $key !== self::SELECT_URL) {
      throw new NutException('Cookie / Nut selector not passing correct key');
    }
  }

  private function build_nuts() {
    if ($this->status == self::STATUS_BUILT) {
      return;
    }
    try {
      $this->is_clean();
      $this->clean = FALSE;
      $this->source_raw_nuts();
      $this->encode_raw_nuts();
      $this->encrypt_wrapper();
      $this->save();
      $this->set_cookie();
      $this->status = self::STATUS_BUILT;
    }
    catch (NutException $e) {
      $this->error_code = $e->getCode();
      $this->error_message = $e->getMessage();
    }
  }

  private function fetch_nuts($cookie_expected = FALSE) {
    if (!$this->clean) {
      return;
    }
    try {
      $this->is_clean();
      $this->clean = FALSE;
      $this->status = self::STATUS_FETCH;
      $this->nut[self::SELECT_URL] = $this->fetch_url_nut();
      if ($cookie_expected) {
        $this->nut[self::SELECT_COOKIE] = $this->fetch_cookie_nut();
      }
      $this->decrypt_wrapper($cookie_expected);
      $this->decode_encoded_nuts($cookie_expected);
      $this->load();
      $this->status = self::STATUS_DECODED;
      $this->validate_nuts($cookie_expected);
    }
    catch (NutException $e) {
      $this->error_code = $e->getCode();
      $this->error_message = $e->getMessage();
    }
  }

  private function source_raw_nuts() {
    $this->raw[self::SELECT_URL] = array(
      'time' => $this->request_time,
      'ip' => $this->_get_ip_address(),
      'counter' => $this->counter('sqrl_nut'),
      'random' => $this->_get_random_bytes(4),
    );
    $this->raw[self::SELECT_COOKIE] = $this->raw[self::SELECT_URL];
  }

  private function encode_raw_nuts() {
    $keys = array(self::SELECT_COOKIE, self::SELECT_URL);
    foreach ($keys as $key) {
      $ref = &$this->raw[$key];
      //format bytes
      $output = pack('LLL', $ref['time'], $this->_ip_to_long($ref['ip']), $ref['counter']) . $ref['random'];
      $this->encoded[$key] = $output;
    }
  }

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
        $this->is_raw_nut_expired($key);
      }
      else {
        throw new NutException(SQRL::get_message()->format('Nut string length incorrect: @len', array('@len' => strlen($ref[$key]))));
      }
    }
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
    return $this->_bytes_extract($bytes, 12, 4);
  }

  private function encrypt_wrapper() {
    $keys = array(self::SELECT_URL, self::SELECT_COOKIE);
    foreach ($keys as $key) {
      $ref = &$this->encoded[$key];
      $data = $this->encrypt($ref, $key);
      $this->nut[$key] = strtr($data, array('+' => '-', '/' => '_', '=' => ''));
    }
  }

  private function decrypt_wrapper() {
    $keys = array(self::SELECT_URL, self::SELECT_COOKIE);
    foreach ($keys as $key) {
      $ref = &$this->nut[$key];
      $data = strtr($ref, array('-' => '+', '_' => '/')) . '==';
      $this->encoded[$key] = $this->decrypt($data, $key);
    }
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
  }

  private function is_dirty() {
    if ($this->clean) {
      throw new NutException('Dirty nut expected clean nut found');
    }
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
    foreach ($this->raw[self::SELECT_URL] as $key => $value) {
      if ($this->raw[self::SELECT_COOKIE][$key] != $value) {
        throw new NutException('Nut in url and cookie raw parameter arrays do not match');
      }
    }
  }

  private function is_match_encoded_nuts() {
    $str_url = $this->encoded[self::SELECT_URL];
    $str_cookie = $this->encoded[self::SELECT_COOKIE];
    if (!$this->time_safe_strcomp($str_url, $str_cookie)) {
      throw new NutException('Nut in url and cookie encoded strings do not match');
    }
  }

  private function is_raw_nut_expired($key) {
    $this->nut_key($key);
    $nut = $this->get_raw_nut($key);
    if ($nut['time'] + self::NUT_LIFETIME < $this->request_time) {
      $this->del_cookie();
      throw new NutException('Nut time validity expired');
    }
  }

  #endregion

}

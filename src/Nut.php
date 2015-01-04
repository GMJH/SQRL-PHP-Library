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
 * This class defines the main structure for a SQRL nut object, encoding,
 * decoding and validation. This is only accessible and used by the Nut class,
 * once for the url nut and optionally once for the cookie nut.
 */
class Nut extends Common {

  const MODE_BUILD = 'build';
  const MODE_FETCH = 'fetch';
  const IS_COOKIE  = FALSE;

  const STATUS_INITED = 'inited';
  const STATUS_VALID = 'validated';
  const STATUS_INVALID = 'invalid';
  const STATUS_BUILT = 'built';
  const STATUS_FETCHED = 'fetched';
  const STATUS_DECODED = 'decoded';
  const STATUS_NOCOOKIE = 'nocookie';

  // @var Nut $wrapper
  protected $wrapper;

  // @var NutCookie $cookie_nut
  private $cookie_nut;

  private $mode = self::MODE_BUILD;

  protected $nut_public;
  protected $nut_encoded;
  protected $nut_raw;

  private $status = self::STATUS_INITED;

  private $error_code = 0;
  private $error_message = '';

  /**
   * @param SQRL $wrapper
   */
  public function __construct($wrapper) {
    $this->wrapper = $wrapper;
  }

  public function requires_cookie() {
    $this->cookie_nut = new NutCookie($this->wrapper);
    $this->cookie_nut->set_url_nut($this);
  }

  final public function is_valid() {
    return ($this->status != self::STATUS_INVALID);
  }

  final public function get_error_message() {
    return $this->error_message;
  }

  final public function get_nut() {
    $this->build();
    return $this->nut_public;
  }

  public function fetch() {
    $this->mode = self::MODE_FETCH;
    if ($this->status == self::STATUS_FETCHED) {
      return;
    }
    try {
      $this->nut_public = $this->fetch_nut();
      $this->decrypt();
      $this->decode();
      $this->validate_expiration();
      $this->load();

      if ($this->cookie_nut instanceof NutCookie) {
        $this->cookie_nut->fetch();
      }
      $this->status = self::STATUS_FETCHED;
    }
    catch (NutException $e) {
      // TODO: Logging.
      $this->status = self::STATUS_INVALID;
      $this->error_code = $e->getCode();
      $this->error_message = $e->getMessage();
    }
  }

  public function build() {
    if ($this->mode != self::MODE_BUILD || $this->status == self::STATUS_BUILT) {
      return;
    }
    try {
      $this->requires_cookie();
      $this->status = self::STATUS_BUILT;
      $this->nut_raw = array(
        'time' => $this->get_request_time(),
        'ip' => $this->get_ip_address(),
        'counter' => $this->wrapper->counter(),
        'random' => $this->random_bytes(4),
      );
      $this->encode();
      $this->encrypt();
      $this->wrapper->save($this->wrapper->get_operation_params());

      if ($this->cookie_nut instanceof NutCookie) {
        $this->cookie_nut->build();
      }
    }
    catch (NutException $e) {
      // TODO: Logging.
      $this->status = self::STATUS_INVALID;
      $this->error_code = $e->getCode();
      $this->error_message = $e->getMessage();
    }
  }

  protected function fetch_nut() {
    $nut = isset($_GET['nut']) ? $_GET['nut'] : FALSE;
    if (!$nut) {
      throw new NutException('Nut missing from GET request');
    }
    return $nut;
  }

  protected function encrypt() {
    $data = $this->base64_encode($this->wrapper->encrypt($this->nut_encoded, $this::IS_COOKIE));
    $this->nut_public = strtr($data, array('+' => '-', '/' => '_', '=' => ''));
  }

  private function decrypt() {
    $ref = $this->nut_public;
    $data = $this->base64_decode(strtr($ref, array('-' => '+', '_' => '/')) . '==');
    $this->nut_encoded = $this->wrapper->decrypt($data, $this::IS_COOKIE);

  }

  private function encode() {
    $ref = $this->nut_raw;
    //format bytes
    $this->nut_encoded = pack('LLL', $ref['time'], $this->_ip_to_long($ref['ip']), $ref['counter']) . $ref['random'];
  }

  private function decode() {
    if (strlen($this->nut_encoded) == 16) {
      $this->nut_raw = array(
        'time' => $this->decode_time($this->nut_encoded),
        'ip' => $this->decode_ip($this->nut_encoded),
        'counter' => $this->decode_counter($this->nut_encoded),
        'random' => $this->decode_random($this->nut_encoded),
      );
    }
    else {
      throw new NutException(SQRL::get_message()->format('Nut string length incorrect: @len', array('@len' => strlen($this->nut_encoded))));
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

  private function validate_expiration() {
    if ($this->is_timeout($this->nut_raw['time'])) {
      $this->wrapper->del_cookie();
      throw new NutException('Nut time validity expired');
    }
  }

  private function load() {
    $params = $this->wrapper->load();
    if (empty($params)) {
      throw new NutException('No params received from implementing framework');
    }
    if (empty($params['op'])) {
      throw new NutException('Wrong params received from implementing framework');
    }
    if (empty($params['ip'])) {
      throw new NutException('Wrong params received from implementing framework');
    }
    if (!isset($params['params']) || !is_array($params['params'])) {
      throw new NutException('Wrong params received from implementing framework');
    }
    $this->wrapper->set_operation($params['op']);
    $this->wrapper->set_nut_ip_address($params['ip']);
    $this->wrapper->set_operation_params($params['params']);
  }

  private function random_bytes($count) {
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

}

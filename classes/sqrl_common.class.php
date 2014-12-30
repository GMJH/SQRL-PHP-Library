<?php

define('SQRL_CRLF', "\r\n");
define('SQRL_MSG_ERROR', 1);
define('SQRL_MSG_WARNING', 2);
define('SQRL_MSG_INFO', 3);

/**
 * A common class for helper functions in assistance of SQRL nut and client operations
 *
 * @author ramriot
 */
abstract class sqrl_common {

  protected $b = '';

  public function __construct() {
    //Nothing to do at this point.
  }

  abstract public function set_message($message, $type);

  /**
   * helper function to do token replacement in strings
   *
   * @param $string
   * @param array $args
   * @return string
   */
  static public function format_string($string, array $args = array()) {
    // Transform arguments before inserting them.
    foreach ($args as $key => $value) {
      switch ($key[0]) {
        case '@':
          // Escaped only.
          $args[$key] = self::check_plain($value);
          break;

        case '!':
          // Pass-through.
          break;

        case '%':
        default:
          // Escaped and placeholder.
          $args[$key] = self::placeholder($value);
          break;

      }
    }
    return strtr($string, $args);
  }

  /**
   * @param $text
   * @return string
   */
  static public function check_plain($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  /**
   * @param $text
   * @return string
   */
  static public function placeholder($text) {
    return '<em class="placeholder">' . self::check_plain($text) . '</em>';
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
   * function got fetch a series of high entropy psudo random bytes
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
   * @param $ip
   * @return int|number
   */
  protected function _ip_to_long($ip) {
    if (strlen($ip) > 15) {
      //for IPV6 output long from ast 8 bytes of sha1
      return hexdec(substr(hash('sha1', $ip, FALSE), -8));
    }
    else {
      return ip2long($ip);
    }
  }

  /**
   * @param $ip
   * @return string
   */
  protected function _long_to_ip($ip) {
    return long2ip((float) $ip);
  }

  /**
   * @param $bytes
   * @param $start
   * @param $len
   * @return string
   */
  protected function _bytes_extract($bytes, $start, $len) {
    $result = '';
    while ($len > 0) {
      $result .= $bytes[$start];
      $start++;
      $len--;
    }
    return $result;
  }

  /**
   * Get the value of a key in the $_SERVER array or the string unknown if the
   * key is not set.
   *
   * @param string $key
   * @return string
   */
  public function _get_server_value($key) {
    return isset($_SERVER[$key]) ? $_SERVER[$key] : 'unknown';
  }

  /**
   * Returns a URL safe base64 encoded version of the string.
   *
   * @param $string
   * @return string
   */
  function base64_encode($string) {
    $data = base64_encode($string);
    // Modify the output so it's safe to use in URLs.
    return strtr($data, array('+' => '-', '/' => '_', '=' => ''));
  }

  /**
   * Returns the base64 decoded version of the URL safe string.
   *
   * @param $string
   * @return string
   */
  function base64_decode($string) {
    $string = strtr($string, array('-' => '+', '_' => '/'));
    return base64_decode($string);
  }

  /**
   * @param $param
   * @param $key
   * @return array
   */
  public function _decode_parameter($param, $key) {
    $string = $this->base64_decode($param);
    $values = explode(SQRL_CRLF, $string);
    $vars = array();
    foreach ($values as $value) {
      if (!empty($value)) {
        $parts = explode('=', $value);
        $k = array_shift($parts);
        $vars[$key][$k] = implode('=', $parts);
      }
    }
    return $vars;
  }

  /**
   * @param $decimal_i
   * @return string
   */
  protected function dec2bin_i($decimal_i) {
    $binary_i = '';
    do {
      $binary_i = substr($decimal_i, -1) % 2 . $binary_i;
      $decimal_i = bcdiv($decimal_i, '2', 0);
    } while (bccomp($decimal_i, '0'));

    return ($binary_i);
  }

  /**
   * @param $y
   * @return mixed
   */
  protected function encodeint($y) {
    $bits = substr(str_pad(strrev($this->dec2bin_i($y)), $this->b, '0', STR_PAD_RIGHT), 0, $this->b);

    return $this->bitsToString($bits);
  }

  /**
   * @param $bits
   * @return string
   */
  protected function bitsToString($bits) {
    // TODO: Implemantation.
    return $bits;
  }

  /**
   * @param $h
   * @param $i
   * @return int
   */
  protected function bit($h, $i) {
    return (ord($h[(int) bcdiv($i, 8, 0)]) >> substr($i, -3) % 8) & 1;
  }

  /**
   * @param $s
   * @return int|string
   */
  protected function decodeint($s) {
    $sum = 0;
    for ($i = 0; $i < $this->b; $i++) {
      $sum = bcadd($sum, bcmul(bcpow(2, $i), $this->bit($s, $i)));
    }

    return $sum;
  }

  /**
   * @param $sig
   * @param $pk
   * @return bool
   */
  protected function ed25519_checkvalid($sig, $pk) {
    // TODO: needs implementation or external library.
    return TRUE;
  }

  /**
   * @param $s
   * @return string
   */
  protected function encodepoint($s) {
    // TODO: Implementation.
    return $s;
  }

  /**
   * @param $s
   * @return string
   */
  protected function decodepoint($s) {
    // TODO: Implementation.
    return $s;
  }

  /**
   * @param $s
   * @return string
   */
  protected function Hint($s) {
    // TODO: Implementation.
    return $s;
  }

  /**
   * @param $b
   * @param $s
   * @return string
   */
  protected function scalarmult($b, $s) {
    // TODO: Implementation.
    return '';
  }

  /**
   * @param $b
   * @param $s
   * @return string
   */
  protected function edwards($b, $s) {
    // TODO: Implementation.
    return '';
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
      throw new InvalidArgumentException('This function cannot safely compare against an empty given string');
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

}

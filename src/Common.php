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
 * A common class for helper functions in assistance of SQRL nut and client operations
 *
 * @author ramriot
 */
abstract class Common {

  public function get_request_time() {
    static $time;
    if (!isset($time)) {
      if (defined('REQUEST_TIME')) {
        $time = REQUEST_TIME;
      }
      else if (isset($_SERVER['REQUEST_TIME'])) {
        $time = $_SERVER['REQUEST_TIME'];
      }
      else {
        $time = time();
      }
    }
    return $time;
  }

  public function is_timeout($timestamp) {
    return ($timestamp + $this->get_lifetime() < $this->get_request_time());
  }

  public function get_timeout() {
    return ($this->get_lifetime() + $this->get_request_time());
  }

  public function get_lifetime() {
    return $this->set_lifetime();
  }

  public function set_lifetime($lifetime = NULL) {
    static $time;
    if (isset($lifetime)) {
      $time = $lifetime;
    }
    else if (!isset($time)) {
      $time = 600;
    }
    return $time;
  }

  /**
   * Get the value of a key in the $_SERVER array or the string unknown if the
   * key is not set.
   *
   * @param string $key
   * @return string
   */
  function get_server_value($key) {
    return isset($_SERVER[$key]) ? $_SERVER[$key] : 'unknown';
  }

  /**
   * Get the value of a key in the $_SERVER array or the string unknown if the
   * key is not set.
   *
   * @param string $key
   * @return string
   */
  function get_post_value($key) {
    return isset($_POST[$key]) ? $_POST[$key] : '';
  }

  function get_ip_address() {
    // TODO: Should private range and/or reserved range be allowed or not?
    // $filter = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    $filter = FALSE;
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

          if (!$filter || filter_var($ip_address, FILTER_VALIDATE_IP, $filter)) {
            return $ip_address;
          }
        }
      }
    }
    return FALSE;
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
   * return a unsigned integer repesenting an IPV4/6 address
   * 32 bit for IPV4 and 128 bit for IPV6
   *
   * @param $ip
   * @return int|number
   */
  protected function _ip_to_long($ip) {
    if (version_compare(phpversion(), '5.1.0', '<')) {
      // php version isn't high enough
      if (strlen($ip) > 15) {
        //for IPV6 output long from last 8 bytes of sha1
        return hexdec(substr(hash('sha1', $ip, FALSE), -8));
      }
      else {
        return ip2long($ip);
      }
    }
    //PHP_VERSION >= 5.1.0
    return inet_pton($ip);
  }

  /**
   * @param $ip
   * @return string
   */
  protected function _long_to_ip($ip) {
    return long2ip((float) $ip);
  }

  /**
   * Extract specific bytes from a byteString
   *
   * @param string $bytes
   *  STRING of bytes
   * @param int $start
   *  INT string pointer to first byte to be extracted
   * @param int $len
   *  INT number of butes to extract
   * @return string
   *  STRING of bytes
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

  protected $b = '';

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
    // TODO: Implementation.
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

}

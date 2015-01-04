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
abstract class SQRL extends Common {

  const PATH_PREFIX = 'sqrl/';
  const PATH_CLIENT = '';

  private $nut_ip_address;
  private $operation = 'login';
  private $params = array();
  private $base_url;
  private $url;
  private $scheme;

  // @var NutURL $nut
  private $nut;
  // @var Message $message
  static private $message;

  public function __construct($fetch, $cookie_expected = FALSE) {
    $this->base_url = strtolower($this->build_base_url());
    $this->scheme = $this->use_secure_connection() ? 'sqrl' : 'qrl';
    $this->nut = new Nut($this);
    if ($cookie_expected) {
      $this->nut->requires_cookie();
    }
    if ($fetch) {
      $this->nut->fetch();
    }
  }

  /**
   * @return \JurgenhaasRamriot\SQRL\Message
   */
  public static function get_message() {
    if (!isset(self::$message)) {
      self::$message = new Message();
    }
    return self::$message;
  }

  abstract protected function build_base_url();
  abstract public function is_secure_connection_available();
  abstract public function encrypt($data, $is_cookie);
  abstract public function decrypt($data, $is_cookie);
  abstract public function save($params);
  abstract public function load();
  abstract public function counter();
  abstract public function authenticate($account);
  abstract protected function authenticated();

  #region Main Final ===========================================================

  final public function is_valid() {
    return $this->nut->is_valid();
  }

  final public function get_error_message() {
    return $this->nut->get_error_message();
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
      $suffix[] = 'nut=' . $this->get_nut();
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
      $this->url = $this->scheme . '://' . $base_url . $this->get_path(self::PATH_CLIENT, TRUE, FALSE, $requires_leading_slash);
    }
    return $this->url;
  }

  final public function del_cookie() {
    setcookie('sqrl', '', $this->get_request_time() - 3600, '/', $this->get_base_url());
    unset($_COOKIE['sqrl']);
  }

  final public function get_nut() {
    return $this->nut->get_nut();
  }

  final public function get_nut_ip_address() {
    return $this->nut_ip_address;
  }

  final public function set_nut_ip_address($nut_ip_address) {
    $this->nut_ip_address = $nut_ip_address;
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
      'ip' => $this->get_ip_address(),
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
    $domain_length = strpos($this->base_url, '/');
    return $domain_length ? substr($this->base_url, $domain_length) . '/' : '/';
  }

  #endregion

}

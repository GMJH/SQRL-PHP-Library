<?php
/**
 * SQRL
 *
 * Copyright (c) 2014-2015 Gary Marriott & Jürgen Haas
 *
 * Description
 *
 * Licence     GNU LGPL V3
 *
 * @package    SQRL
 * @author     Jürgen Haas <juergen@paragon-es.de>
 * @author     Gary Marriott <ramriot@gmail.com>
 * @copyright  2014-2015 Gary Marriott & Jürgen Haas
 * @license    http://opensource.org/licenses/LGPL-3.0
 * @link       https://github.com/GMJH/SQRL-PHP-Library
 */

namespace GMJH\SQRL;

/**
 * This class defines the main structure for SQRL nut building,
 * encoding, decoding and validation.
 */
abstract class SQRL extends Common {

  const PATH_PREFIX   = 'sqrl/';
  const PATH_CLIENT   = '';
  const PATH_AJAX     = 'ajax/';
  const PATH_VIEW     = 'view/';
  const PATH_USER     = 'action';
  const PATH_CREATE   = 'create';
  const PATH_QR_IMAGE = 'img';
  const QR_SIZE       = 160;
  const POLL_INTERVAL_INITIAL = 5;
  const POLL_INTERVAL = 2;

  private $nut_ip_address;
  private $operation = 'login';
  private $params = array();
  private $base_url;
  private $url;
  private $scheme;
  private $messages_to_browser = array();

  // @var NutURL $nut
  private $nut;
  // @var Message $message
  static private $message;

  /**
   * @param bool $fetch
   * @param bool $cookie_expected
   * @throws NutException
   * @throws \Exception
   */
  final public function __construct($fetch, $cookie_expected = FALSE) {
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
   * @return \GMJH\SQRL\Message
   */
  public static function get_message() {
    if (!isset(self::$message)) {
      self::$message = new Message();
    }
    return self::$message;
  }

  /**
   * @return string
   */
  abstract protected function build_base_url();

  /**
   * @return bool
   */
  abstract public function is_secure_connection_available();

  /**
   * @param string $data
   * @param bool $is_cookie
   * @return string
   */
  abstract public function encrypt($data, $is_cookie);

  /**
   * @param string $data
   * @param bool $is_cookie
   * @return string
   */
  abstract public function decrypt($data, $is_cookie);

  /**
   * @param array $params
   */
  abstract public function save($params);

  /**
   * @return array
   */
  abstract public function load();

  /**
   * @param Account $account
   */
  abstract public function authenticate($account);

  /**
   * @return bool
   */
  abstract protected function authenticated();

  /**
   * @param Client $client
   * @return bool
   */
  abstract public function create_new_account($client);

  #region Main Final ===========================================================

  /**
   * @return bool
   */
  final public function is_valid() {
    return isset($this->nut) ? $this->nut->is_valid() : FALSE;
  }

  /**
   * @return bool
   */
  final public function is_expired() {
    return isset($this->nut) ? $this->nut->is_expired() : FALSE;
  }

  /**
   * @return string
   */
  final public function get_error_message() {
    return isset($this->nut) ? $this->nut->get_error_message() : '';
  }

  /**
   * @return bool
   */
  final public function is_authenticated() {
    if ($this->authenticated()) {
      $this->del_cookie();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @return string
   */
  final public function get_base_url() {
    return $this->base_url;
  }

  /**
   * @param string $path
   * @param bool $include_nut
   * @param bool $include_base_path
   * @param bool $requires_leading_slash
   * @return string
   */
  final public function get_path($path, $include_nut = TRUE, $include_base_path = TRUE, $requires_leading_slash = TRUE) {
    $prefix = $include_base_path ? $this->get_base_path() : '/';
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
    $separator = strpos($path, '?') ? '&' : '?';
    $suffix = empty($suffix) ? '' : $separator . implode('&', $suffix);
    return $prefix . $this::PATH_PREFIX . $path . $suffix;
  }

  /**
   * @return string
   */
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
      $this->url = $this->scheme . '://' . $base_url . $this->get_path($this::PATH_CLIENT, TRUE, FALSE, $requires_leading_slash);
    }
    return $this->url;
  }

  /**
   *
   */
  final public function del_cookie() {
    setcookie('sqrl', '', $this->get_request_time() - 3600, $this->get_base_path(), $this->get_domain());
    unset($_COOKIE['sqrl']);
  }

  /**
   * @return string
   */
  final public function get_nut() {
    return $this->nut->get_nut();
  }

  /**
   * @return string
   */
  final public function get_nut_ip_address() {
    return $this->nut_ip_address;
  }

  /**
   * @param string $nut_ip_address
   */
  final public function set_nut_ip_address($nut_ip_address) {
    $this->nut_ip_address = $nut_ip_address;
  }

  /**
   * @return string
   */
  final public function get_operation() {
    return $this->operation;
  }

  /**
   * @param string $operation
   */
  final public function set_operation($operation) {
    $this->operation = $operation;
  }

  /**
   * @param string $key
   * @return mixed
   */
  final public function get_operation_param($key) {
    return empty($this->params[$key]) ? FALSE : $this->params[$key];
  }

  /**
   * @return array
   */
  final public function get_operation_params() {
    return array(
      'op' => $this->get_operation(),
      'ip' => $this->get_ip_address(),
      'params' => $this->params,
      'messages to browser' => $this->messages_to_browser,
    );
  }

  /**
   * @param string $key
   * @param mixed $value
   */
  final public function set_operation_param($key, $value) {
    $this->params[$key] = $value;
  }

  /**
   * @param array $values
   */
  final public function set_operation_params($values) {
    foreach ($values as $key => $value) {
      $this->set_operation_param($key, $value);
    }
  }

  /**
   * @return string
   */
  final public function get_icon() {
    return $this->render_image('data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/includes/icon.png')));
  }

  /**
   * @param string $src
   * @return string
   */
  private function render_image($src) {
    $size = $this->get_qr_size();
    return '<img src="' . $src . '" alt="SQRL" title="SQRL" height="' . $size . '" width="' . $size . '">';
  }

  #endregion

  #region Main (potential overwrite) ===========================================

  /**
   * @return bool
   */
  public function use_secure_connection() {
    return $this->is_secure_connection_available();
  }

  /**
   * @return int
   */
  public function get_connection_port() {
    return $this->is_secure_connection_available() ? 443 : 80;
  }

  /**
   * return number of microseconds since last unix timestamp change,
   * scaled  by 10 modulo 65536, see Apache Module mod_unique_id for
   * justification that this is adequately request unique.
   *
   * @return int
   */
  public function counter() {
    list($usec, $sec) = explode(" ", microtime());
    return (intval($usec * 100000)) % 65536;
  }

  /**
   * @return bool
   */
  public function is_page_cached() {
    return FALSE;
  }

  /**
   * @return bool
   */
  public function is_auto_create_account() {
    return FALSE;
  }

  /**
   * @return int
   */
  public function get_qr_size() {
    return $this::QR_SIZE;
  }

  /**
   * @param bool $initial
   * @return int
   */
  public function get_poll_interval($initial = FALSE) {
    return $initial ? $this::POLL_INTERVAL_INITIAL : $this::POLL_INTERVAL;
  }

  /**
   * @return string
   */
  public function get_authenticated_destination() {
    return $this->get_path($this::PATH_USER, FALSE);
  }

  /**
   *
   */
  public function poll() {
    $result = array();

    // Check for messages and status from client.
    $message = '';
    $stop_polling = FALSE;
    foreach ($this->messages_to_browser as $msg) {
      if ($msg['type'] == 'destination') {
        $result['location'] = $msg['message'];
      }
      else {
        $message .= '<div class="sqrl-message sqrl-message-' . $msg['type'] . '">' . $msg['message'] . '</div>';
      }
      if (!empty($msg['stop polling'])) {
        $stop_polling = TRUE;
      }
    }
    if (!empty($message)) {
      $result['msg'] = $message;
    }
    if (!$stop_polling) {
      $this->messages_to_browser = array();
      $this->save($this->get_operation_params());
    }

    if ($stop_polling || !$this->is_valid()) {
      $result['stopPolling'] = TRUE;
    }
    else if ($this->is_authenticated()) {
      $destination = $this->get_authenticated_destination();
      if ($destination) {
        $result['location'] = $destination;
      }
      $result['stopPolling'] = TRUE;
    }
    else {
      $result['stopPolling'] = FALSE;
    }
    header('Content-Type: application/json');
    echo json_encode($result);
  }

  /**
   * @param string $operation
   * @param bool $force
   * @param bool $json
   * @return string
   */
  public function get_markup($operation, $force = FALSE, $json = FALSE) {
    $this->set_operation($operation);

    if (!$force && $this->is_page_cached()) {
      $image = $this->get_icon();
      $markup = '<div id="sqrl-cache"><a href="' . $this->get_path($this::PATH_VIEW . $operation, FALSE) . '">' . $image . '</a></div>';
    }
    else {
      $image = $this->render_image($this->get_path($this::PATH_QR_IMAGE));
      $markup = '<div id="sqrl-' . $operation . '" class="sqrl ' . $operation . '"><a href="' . $this->get_nut_url() . '">' . $image . '</a></div>';;
    }

    $vars = array(
      'url' => array(
        'markup' => $this->get_path($this::PATH_AJAX . 'markup', FALSE),
        'poll' => $this->get_path($this::PATH_AJAX . 'poll'),
      ),
      'pollIntervalInitial' => $this->get_poll_interval(TRUE) * 1000,
      'pollInterval' => $this->get_poll_interval(FALSE) * 1000,
    );
    // Add JavaScript and CSS to the page.
    if (!$force) {
      $script = '<script type="text/javascript">' . "\n";
      $script .= '<!--//--><![CDATA[//><!--' . "\n";
      $script .= 'var sqrl = JSON.parse(' . "'" . json_encode($vars) . "');\n";
      $script .= file_get_contents(__DIR__ . '/includes/sqrl.js');
      $script .= '//--><!]]>' . "\n";
      $script .= '</script>';
      $markup = $script . $markup;
    }

    if ($json) {
      header('Content-Type: application/json');
      return json_encode(array(
        'vars' => $vars,
        'markup' => $markup,
      ));
    }
    else {
      return $markup;
    }
  }

  /**
   *
   */
  public function get_qr_image() {
    if (!$this->is_valid()) {
      header('Status: 404 Not Found');
      print '';
      exit;
    }

    $string = $this->get_nut_url();
    header('Content-type: image/png');
    include_once __DIR__ . '/qrcode/phpqrcode.php';
    \QRcode::png($string, FALSE, QR_ECLEVEL_L, 3, 4, FALSE);
  }

  /**
   * @return string
   */
  public function toDebug() {
    return json_encode(array(
      'nut_ip_address' => $this->nut_ip_address,
      'nut' => empty($this->nut) ? 'null' : $this->nut->toDebug(),
    ));
  }

  #endregion

  #region Internal =============================================================

  /**
   * @return string
   */
  public function get_domain() {
    $domain_length = strpos($this->base_url, '/');
    return $domain_length ? substr($this->base_url, 0, $domain_length) : $this->base_url;
  }

  /**
   * @return string
   */
  public function get_base_path() {
    $domain_length = strpos($this->base_url, '/');
    return $domain_length ? substr($this->base_url, $domain_length) . '/' : '/';
  }

  /**
   * @param Client $client
   */
  public function ask_to_create_new_account($client) {
    $this->set_operation_param('validated nut', $this->get_nut());
    $this->set_operation_param('client', $client);
    $this->add_message_to_browser('destination', $this->get_path($this::PATH_CREATE), TRUE);
  }

  /**
   * @param string $type
   * @param string $message
   * @param bool $stop_polling
   */
  public function add_message_to_browser($type, $message, $stop_polling) {
    $this->messages_to_browser[] = array(
      'type' => $type,
      'message' => $message,
      'stop polling' => $stop_polling,
    );
    $this->save($this->get_operation_params());
  }

  /**
   * @param array $messages
   */
  public function add_messages_to_browser($messages) {
    $this->messages_to_browser = $messages;
  }

  #endregion

}

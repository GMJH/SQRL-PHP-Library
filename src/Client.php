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
 * A class to encompass the processing and validation of incoming
 * SQRL POST parameters
 */
abstract class Client extends Common {

  const CRLF = "\r\n";

  const FLAG_IDK_MATCH = 0x01;
  const FLAG_PIDK_MATCH = 0x02;
  const FLAG_IP_MATCH = 0x04;
  const FLAG_ACCOUNT_ENABLED = 0x08;
  const FLAG_ACCOUNT_LOGGED_IN = 0x10;
  const FLAG_ACCOUNT_CREATION_ALLOWED = 0x20;
  const FLAG_COMMAND_FAILURE = 0x40;
  const FLAG_FAILURE = 0x80;

  // @var SQRL $sqrl
  protected $sqrl;
  // @var Account $account
  protected $account;

  private $client_sigs;
  private $client_header;
  private $client_vars;
  private $server_vars;
  private $validation_string;

  private $valid = FALSE;
  private $http_code = 403;
  private $message = '';
  private $tif = 0;
  private $response = array();
  private $fields = array();

  /**
   * @param SQRL $sqrl
   */
  public function __construct($sqrl) {
    $this->sqrl = $sqrl;
    SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Incoming client request');
    $this->process();
    SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Incoming client request processed', array('client class' => $this,));
    if ($this->valid) {
      $commands = $this->commands_determine();
      $this->commands_execute($commands);
    }
    $this->respond();
  }

  /**
   * @return string
   */
  public function toDebug() {
    return json_encode(array(
      'valid' => $this->valid ? 'yes' : 'no',
      'http_code' => $this->http_code,
      'message' => $this->message,
      'sqrl' => $this->sqrl->toDebug(),
    ));
  }

  #region Main =================================================================

  public function set_message($message, $tif = FALSE) {
    if ($tif) {
      $this->tif |= $tif;
    }
    $this->message = $message;
  }

  public function get_tif() {
    return $this->tif;
  }

  public function get_client_var($key) {
    return isset($this->client_vars[$key]) ? $this->client_vars[$key] : '';
  }

  public function set_response($key, $value) {
    $this->response[$key] = $value;
  }

  abstract public function site_name();
  abstract protected function save($value);
  abstract protected function load();
  abstract protected function find_user_account($key);
  abstract protected function command_create();

  #endregion

  #region Validation ===========================================================

  private function validate() {
    $this->validate_signatures();
    $this->validate_header();
    $this->validate_client_vars();
    $this->validate_server_vars();
    $this->validate_nut();
  }

  private function validate_signatures() {
    // TODO: Check the signature/pub_key mapping.
    $required_signatures = array(
      'ids' => 'idk',
      // 'pids' => 'pidk',
      // 'urs' => 'purs',
    );
    foreach ($required_signatures as $key => $pub_key) {

      // Is the signature present?
      if (empty($this->client_sigs[$key])) {
        throw new ClientException('Missing signature');
      }

      // Is the public key present?
      if (empty($this->client_vars[$pub_key])) {
        throw new ClientException('Missing public key');
      }

      // Validate signature
      $this->ed25519_checkvalid($this->client_sigs[$key], $this->client_vars[$pub_key]);

      // Validate the validation string
      $this->validate_string($this->client_sigs[$key]);
    }
  }

  /**
   * @param $signature
   * @throws ClientException
   */
  private function validate_string($signature) {
    // TODO: Validation of the string's signature.
    if (FALSE) {
      throw new ClientException('Validation string not signed properly');
    }
  }

  private function validate_header() {
    $required_signatures = array();
    foreach ($required_signatures as $key) {

      // Is the header value present?
      if (empty($this->client_header[$key])) {
        throw new ClientException('Missing header value');
      }
    }
  }

  private function validate_client_vars() {
    $required_signatures = array();
    foreach ($required_signatures as $key) {

      // Is the client value present?
      if (empty($this->client_vars[$key])) {
        throw new ClientException('Missing client var');
      }
    }
  }

  private function validate_server_vars() {
    $required_signatures = array();
    foreach ($required_signatures as $key) {

      // Is the server value present?
      if (empty($this->server_vars[$key])) {
        throw new ClientException('Missing server var');
      }
    }
  }

  private function validate_nut() {
    if (!$this->sqrl->is_valid()) {
      throw new ClientException($this->sqrl->get_error_message());
    }

    // Check if this client request is a response to a previous one and validate
    $value = $this->load();
    if ($value) {
      if ($value != $this->encode_response($this->server_vars)) {
        // TODO: Validation currently fails as the order from the client is different from what we sent out.
        // throw new ClientException('Data from client does not match our previous response');
      }
    }
  }

  private function validate_ip_address() {
    if ($this->sqrl->get_nut_ip_address() == $this->get_ip_address()) {
      $this->tif |= self::FLAG_IP_MATCH;
    }
  }

  #endregion

  #region Internal =============================================================

  /**
   * https://www.grc.com/sqrl/semantics.htm: The data to be signed are the two
   * base64url encoded values of the “client=” and “server=” parameters with the
   * “server=” value concatenated to the end of the “client=” value.
   *
   * @throws ClientException
   * @throws \Exception
   */
  private function process() {
    $this->client_sigs = array(
      'ids' => $this->base64_decode($this->get_post_value('ids')),
      // 'pids' => $this->base64_decode($this->get_post_value('pids')),
      // 'urs' => $this->base64_decode($this->get_post_value('urs')),
    );
    $this->client_header = array(
      'host' => $this->get_server_value('HTTP_HOST'),
      'auth' => $this->get_server_value('HTTP_AUTHENTICATION'),
      'agent' => $this->get_server_value('HTTP_USER_AGENT'),
    );
    $this->client_vars = $this->decode_parameter($this->get_post_value('client'));
    $this->server_vars = $this->decode_parameter($this->get_post_value('server'));
    $this->validation_string = $this->get_post_value('client') . $this->get_post_value('server');

    try {
      $this->validate();
    }
    catch (ClientException $e) {
      $this->tif |= self::FLAG_COMMAND_FAILURE;
      $this->message = $e->getMessage();
      throw $e;
    }

    try {
      $this->validate_ip_address();
      $this->authenticate();
    }
    catch (ClientException $e) {
      $this->tif |= self::FLAG_COMMAND_FAILURE;
      $this->message = $e->getMessage();
      throw $e;
    }

    $this->valid = TRUE;
    $this->http_code = 200;
    $this->message = 'Welcome';
  }

  private function authenticate() {
    $current_account = $this->find_user_account_by_key('idk');
    $previous_account = $this->find_user_account_by_key('pidk');

    if (!empty($previous_account)) {
      $this->tif |= self::FLAG_PIDK_MATCH;
      $account = $previous_account;
    }
    if (!empty($current_account)) {
      $this->tif |= self::FLAG_IDK_MATCH;
      $account = $current_account;
      if (!$account->equals($previous_account)) {
        throw new ClientException('Current and previous user accounts do not match');
      }
    }

    if (!isset($account)) {
      if ($this->user_account_register_allowed()) {
        $this->tif |= self::FLAG_ACCOUNT_CREATION_ALLOWED;
      }
      return;
    }

    if ($account->enabled()) {
      $this->tif |= self::FLAG_ACCOUNT_ENABLED;
    }
    if ($account->logged_in()) {
      $this->tif |= self::FLAG_ACCOUNT_LOGGED_IN;
    }

    $this->account = $account;
  }

  private function commands_determine() {
    if ($this->sqrl->get_operation() == 'link') {
      if (empty($this->client_vars['suk']) || empty($this->client_vars['vuk'])) {
        // This is the initial request from the client and we respond such
        // that there is no account yet. This forces the client to send the
        // keys with the next request.
        $commands = array();
      }
      else if ($this->account) {
        // Trying to link a user account to a SQRL identity that's already
        // linked to another account. This needs to fail.
        // TODO: How to respond to the client without disclosing too much information?
        $commands = array();
      }
      else {
        $commands = array('setkey_link');
      }
    }
    else {
      $commands = explode('~', $this->client_vars['cmd']);
    }
    return $commands;
  }

  /**
   * @param array $commands
   * @throws ClientException
   * @throws \Exception
   */
  private function commands_execute($commands) {
    try {
      foreach ($commands as $command) {
        $method = 'command_' . $command;
        if ($this->account && method_exists($this->account, $method)) {
          if ($this->account->{$method}($this)) {
            $this->sqrl->authenticate($this->account);
          }
        }
        else if (method_exists($this, $method)) {
          if ($this->{$method}()) {
            $this->sqrl->authenticate($this->account);
          }
        }
      }
    }
    catch (ClientException $e) {
      $this->tif |= self::FLAG_COMMAND_FAILURE;
      $this->message = $e->getMessage();
      throw $e;
    }
  }

  private function respond() {
    // Build the response body.
    $response = array(
      'ver' => '1',
      'nut' => $this->sqrl->get_nut(),
      'tif' => $this->tif,
      #'qry' => $this->nut->get_path('client/follow-up', $this->nut->get_public_nut(Nut::SELECT_URL)),
      'sfn' => $this->site_name(),
    );
    $response += $this->response;

    // TODO: How do we make the following secure?
    if ($this->sqrl->is_secure_connection_available()) {
      $response['lnk'] = $this->sqrl->get_path('action');
    }

    $msg = $this->message;
    foreach ($this->fields as $type => $label) {
      $msg .= '~' . $type . ':' . $label;
    }
    $response['ask'] = $msg;

    $base64 = $this->encode_response($response);

    SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Server response', array('values' => $response, 'base64' => $base64,));
    $this->save($base64);

    $headers = array(
      'charset' => 'utf-8',
      'content-type' => 'text/plain',
      'http_code', $this->http_code,
    );
    foreach ($headers as $key => $value) {
      header($key . ': ' . $value);
    }
    print('server=' . $base64 . self::CRLF);
    exit;
  }

  private function encode_response($values) {
    $output = array();
    foreach ($values as $key => $value) {
      $output[] = $key . '=' . $value;
    }
    return $this->base64_encode(implode(self::CRLF, $output) . self::CRLF);
  }

  /**
   * @param $param
   * @return array
   */
  private function decode_parameter($param) {
    $string = $this->base64_decode($param);
    $values = explode(self::CRLF, $string);
    $vars = array();
    foreach ($values as $value) {
      if (!empty($value)) {
        $parts = explode('=', $value);
        $k = array_shift($parts);
        $vars[$k] = implode('=', $parts);
      }
    }
    return $vars;
  }

  /**
   * @param $key_type
   * @return Account
   */
  private function find_user_account_by_key($key_type) {
    if (!empty($this->client_vars[$key_type])) {
      return $this->find_user_account($this->client_vars[$key_type]);
    }
    return NULL;
  }

  protected function user_account_register_allowed() {
    return TRUE;
  }

  #endregion

  #region Crypto ===============================================================

  /**
   * @param $s
   * @param $m
   * @param $pk
   * @return bool
   * @throws Exception
   */
  private function ed25519_something($s, $m, $pk) {
    if (strlen($s) != $this->b / 4) {
      throw new ClientException('Signature length is wrong');
    }
    if (strlen($pk) != $this->b / 8) {
      throw new ClientException('Public key length is wrong: ' . strlen($pk));
    }
    $R = $this->decodepoint(substr($s, 0, $this->b / 8));
    try {
      $A = $this->decodepoint($pk);
    }
    catch (Exception $e) {
      return FALSE;
    }
    $S = $this->decodeint(substr($s, $this->b / 8, $this->b / 4));
    $h = $this->Hint($this->encodepoint($R) . $pk . $m);

    return $this->scalarmult($this->b, $S) == $this->edwards($R, $this->scalarmult($A, $h));
  }


  /**
   * @param $sig
   * @param $pk
   * @return bool
   */
  private function ed25519_checkvalid($sig, $pk) {
    // TODO: needs implementation or external library.
    return TRUE;
  }

  /**
   * @param $s
   * @return string
   */
  private function encodepoint($s) {
    // TODO: Implementation.
    return $s;
  }

  /**
   * @param $s
   * @return string
   */
  private function decodepoint($s) {
    // TODO: Implementation.
    return $s;
  }

  /**
   * @param $s
   * @return string
   */
  private function Hint($s) {
    // TODO: Implementation.
    return $s;
  }

  /**
   * @param $b
   * @param $s
   * @return string
   */
  private function scalarmult($b, $s) {
    // TODO: Implementation.
    return '';
  }

  /**
   * @param $b
   * @param $s
   * @return string
   */
  private function edwards($b, $s) {
    // TODO: Implementation.
    return '';
  }

  #endregion

}

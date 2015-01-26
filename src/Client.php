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
  const FLAG_STALE_NUT = 0x100;

  // @var SQRL $sqrl
  protected $sqrl;
  // @var Crypto $crypto
  protected $crypto;
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
   * @param Crypto $crypto
   */
  final public function __construct($sqrl, Crypto $crypto = NULL) {
    $this->sqrl = $sqrl;
    $this->crypto = new CryptoSelect($crypto);
    $this->get_post_value('dummy');
    SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Incoming client request');
    $this->process();
    SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Incoming client request processed', array('client class' => $this,));
    if ($this->valid) {
      $commands = $this->commands_determine();
      $this->commands_execute($commands);
    }
    $this->respond();
  }


  #region Main (potential overwrite) ===========================================

  /**
   * @param string $message
   * @param int $tif
   */
  public function set_message($message, $tif = NULL) {
    if (isset($tif)) {
      $this->tif |= $tif;
    }
    $this->message = $message;
  }

  /**
   * @return int
   */
  public function get_tif() {
    return $this->tif;
  }

  /**
   * @return SQRL
   */
  public function get_sqrl() {
    return $this->sqrl;
  }

  /**
   * @param string $key
   * @return string
   */
  public function get_client_var($key) {
    return isset($this->client_vars[$key]) ? $this->client_vars[$key] : '';
  }

  /**
   * @param string $key
   * @param string $value
   */
  public function set_response($key, $value) {
    if (!empty($value)) {
      $this->response[$key] = $value;
    }
  }

  /**
   * @return bool
   */
  public function user_account_register_allowed() {
    return TRUE;
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
      'client' => $this->client_vars,
      'server' => $this->server_vars,
      'signatures' => $this->client_sigs,
      'header' => $this->client_header,

    ));
  }

  /**
   * @return string
   */
  abstract public function site_name();

  /**
   * @param mixed $value
   */
  abstract protected function save($value);

  /**
   * @return mixed
   */
  abstract protected function load();

  /**
   * @param SQRL $sqrl
   * @return Account
   */
  abstract protected function get_link_account($sqrl);

  /**
   * @param string $key
   * @return Account
   */
  abstract protected function find_user_account($key);

  /**
   * @param string $key
   * @param bool $just_active
   * @return Account
   */
  abstract public function find_all_user_accounts($key, $just_active = TRUE);

  #endregion

  #region Validation ===========================================================

  /**
   * @throws ClientException
   */
  private function validate() {
    $this->validate_signatures();
    $this->validate_header();
    $this->validate_client_vars();
    $this->validate_server_vars();
    $this->validate_nut();
  }

  /**
   * @throws ClientException
   */
  private function validate_signatures() {
    $msg = $this->validation_string;
    // Is the message present?
    if (empty($msg)) {
      throw new ClientException('Missing validation string');
    }
    $required_signatures = array(
      'ids' => 'idk',
      // 'pids' => 'pidk',
      // 'urs' => 'purs',
    );
    foreach ($required_signatures as $sig_key => $pub_key) {
      $sig = $this->client_sigs[$sig_key];
      $pk  = $this->client_vars[$pub_key];
      // Is the signature present?
      if (empty($sig)) {
        throw new ClientException('Missing signature');
      }
      // Is the public key present?
      if (empty($pk)) {
        throw new ClientException('Missing public key');
      }
      // Validate signature
      $this->validate_signature($msg, $sig, $pk, $sig_key);
    }
  }

  /**
   * Validate a specific signature:-
   * Default support for php-ext-sqrl ed25519 library at:
   * https://github.com/ramriot/php-ext-sqrl/
   *
   * @param string $msg
   *  plain text of signed message
   * @param string $sig
   *  base64url-encoded signature
   * @param string $pk
   *  base64url-encoded Public Key
   * @param string $sig_key
   *  string value of signature name
   * @throws ClientException
   */
  private function validate_signature($msg, $sig, $pk, $sig_key = 'empty') {
    $box = array(
      'message' => $msg,
      'signature' => $sig,
      'publickey' => $pk,
      'sig_key' => $sig_key
    );
    SQRL::get_message()->log(SQRL_LOG_LEVEL_INFO, 'Signature validation in process', $box);
    /*
    if (!$this->crypto->supported()) {
      throw new ClientException(get_class($this->crypto) . ' validation library extension not supported see README');
      return;
    }
    */
    //Process validation parameters as needed by extension
    $this->crypto->process($box);

    if ($this->crypto->validate()) {
      //Valid signature sqrl_verify( $msg, $sig, $pk )
      SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Signature valid', $box);
    }
    else {
      //Invalid signature
      SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Signature invalid', $box);
      throw new ClientException('Signature validation failed: key=>' . $sig_key);
    }
  }

  /**
   * @throws ClientException
   */
  private function validate_header() {
    $required_signatures = array();
    foreach ($required_signatures as $key) {

      // Is the header value present?
      if (empty($this->client_header[$key])) {
        throw new ClientException('Missing header value');
      }
    }
  }

  /**
   * @throws ClientException
   */
  private function validate_client_vars() {
    $required_signatures = array();
    foreach ($required_signatures as $key) {

      // Is the client value present?
      if (empty($this->client_vars[$key])) {
        throw new ClientException('Missing client var');
      }
    }
  }

  /**
   * @throws ClientException
   */
  private function validate_server_vars() {
    $required_signatures = array();
    foreach ($required_signatures as $key) {

      // Is the server value present?
      if (empty($this->server_vars[$key])) {
        throw new ClientException('Missing server var');
      }
    }
  }

  /**
   * @throws ClientException
   */
  private function validate_nut() {
    if (!$this->sqrl->is_valid()) {
      if ($this->sqrl->is_expired()) {
        $this->tif |= self::FLAG_STALE_NUT;
      }
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

  /**
   *
   */
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
      'ids' => $this->get_post_value('ids'),
      // 'pids' => $this->get_post_value('pids'),
      // 'urs' => $this->get_post_value('urs'),
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
      $this->sqrl->add_message_to_browser('error', 'Invalid client request: ' . $this->message, TRUE);
      return;
    }

    try {
      $this->validate_ip_address();
      $this->authenticate();
    }
    catch (ClientException $e) {
      $this->tif |= self::FLAG_COMMAND_FAILURE;
      $this->message = $e->getMessage();
      $this->sqrl->add_message_to_browser('error', 'Invalid client request: ' . $this->message, TRUE);
      return;
    }

    $this->valid = TRUE;
    $this->http_code = 200;
    $this->message = 'Welcome';
  }

  /**
   * @throws ClientException
   */
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
      // No longer testing if user account creation is allowed here. This
      // should be handled by the server implementation later on.
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

  /**
   * @return array
   */
  private function commands_determine() {
    if ($this->sqrl->get_operation() == 'link') {
      $commands = array('setkey_link');
      // TODO: check with the new workflow, previously this was a 2-step-process.
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
        else {
          $this->command_not_implemented($command);
        }
      }
    }
    catch (ClientException $e) {
      $this->tif |= self::FLAG_COMMAND_FAILURE;

      // Message to be returned to the client.
      $this->message = $e->getMessage();

      // Message to be displayed in the browser.
      $this->sqrl->add_message_to_browser('error', $this->message, TRUE);

      throw $e;
    }
  }

  private function command_query() {
    //$this->tif |= self::FLAG_ACCOUNT_ENABLED;
    return FALSE;
  }

  /**
   * This command is called if the client wants to login but there is no
   * matching user account available yet. This should now start the process to
   * ask the user if they wanted to create a new account - without further
   * interaction with the client.
   *
   * @return bool
   * @throws ClientException
   */
  private function command_login() {
    if (!$this->user_account_register_allowed()) {
      // We can't create new accounts on this server.
      throw new ClientException('Creating new user accounts has been disabled.');
    }

    if ($this->sqrl->is_auto_create_account()) {
      $this->sqrl->create_new_account($this);
    }
    else {
      $this->sqrl->ask_to_create_new_account($this);
    }
    $this->tif |= self::FLAG_IDK_MATCH;
    $this->tif |= self::FLAG_ACCOUNT_ENABLED;

    return FALSE;
  }

  private function command_setkey_link() {
    $account = $this->get_link_account($this->sqrl);
    if ($account->command_setkey($this, TRUE)) {
      $this->sqrl->authenticate($account);
      $this->tif |= self::FLAG_IDK_MATCH;
      $this->tif |= self::FLAG_ACCOUNT_ENABLED;
    }
  }

  /**
   * @param $command
   * @throws ClientException
   */
  private function command_not_implemented($command) {
    throw new ClientException('The command ' . $command . ' is not available and caused your request to fail.');
  }

  /**
   *
   */
  private function respond() {
    // Build the response body.
    $response = array(
      'ver' => '1',
      'nut' => $this->sqrl->get_nut(),
      'tif' => $this->tif,
      'qry' => '/sqrl',//$this->sqrl->get_path($this->sqrl->PATH_CLIENT, FALSE),
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
      'content-type' => 'text/plain', //'application/www-url-encoded',
      'http_code' => $this->http_code,
    );
    foreach ($headers as $key => $value) {
      header($key . ': ' . $value);
    }
    print('server=' . $base64 . self::CRLF);
    exit;
  }

  /**
   * @param array $values
   * @return string
   */
  private function encode_response($values) {
    $output = array();
    foreach ($values as $key => $value) {
      $output[] = $key . '=' . $value;
    }
    return $this->base64_encode(implode(self::CRLF, $output) . self::CRLF);
  }

  /**
   * @param string $param
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
   * @param string $key_type
   *  Either "idk" or "pidk".
   * @return Account
   */
  private function find_user_account_by_key($key_type) {
    if (!empty($this->client_vars[$key_type])) {
      return $this->find_user_account($this->client_vars[$key_type]);
    }
    return NULL;
  }

  #endregion

}

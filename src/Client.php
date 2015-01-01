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
 * A class to encompass the processing and validation of incoming
 * SQRL POST parameters
 */
abstract class Client extends Common {

  const CRLF = "\r\n";

  // @var Nut $nut
  private $nut;
  private $sig_ids;
  private $sig_pids;
  private $sig_urs;

  protected $post = array();
  protected $vars = array();
  protected $sigkeys = array();

  /**
   * @param Nut $nut
   */
  public function __construct($nut) {
    parent::__construct();
    //store the nut with the client
    $this->nut = $nut;
    //fetch post array for processing
    $this->post = $_POST;
    //process post array
    $this->process();


    // TODO: The following only if ... !?
    $nut->authenticate($this->sig_ids, $this->sig_pids, $this->sig_urs);
  }

  /**
   * https://www.grc.com/sqrl/semantics.htm: The data to be signed are the two
   * base64url encoded values of the “client=” and “server=” parameters with the
   * “server=” value concatenated to the end of the “client=” value.
   */
  public function process() {

    $this->sig_ids = $this->base64_decode($this->post['ids']);
    $this->sig_pids = $this->base64_decode($this->post['pids']);
    $this->sig_urs = $this->base64_decode($this->post['urs']);
    //current extent of signatures
    $signatures = array(
      'ids' => $this->sig_ids,
      'pids' => $this->sig_pids,
      'urs' => $this->sig_urs,
    );
    //default vars array to be populated by processors
    $this->vars = array(
      'http_code' => 403,
      'tif' => 0,
      'header' => array(
        'host' => $this->get_server_value('HTTP_HOST'),
        'auth' => $this->get_server_value('HTTP_AUTHENTICATION'),
        'agent' => $this->get_server_value('HTTP_USER_AGENT'),
      ),
      'client' => $this->decode_parameter($this->post['client'], 'client'),
      'server' => $this->decode_parameter($this->post['server'], 'server'),
      'validation_string' => $this->post['client'] . $this->post['server'],
      'signatures' => $signatures,
      'nut' => $_GET['nut'],
      'account' => FALSE,
      'valid' => FALSE,
      'response' => array(),
      'fields' => array(),
    );
  }

  /**
   * @return array
   */
  public function get_vars() {
    return $this->vars;
  }

  /**
   * Are all required signatures present.
   *
   * @param $sig_keys
   *  Array of signature keys that are required or string for single
   * @return Boolean False if required key/s are missing other wise return TRUE
   */
  public function required_keys($sig_keys) {
    $result = TRUE;
    if (is_array($sig_keys)) {
      foreach ($sig_keys as $sig_key) {
        if (!$this->required_key($sig_key)) {
          $result = FALSE;
          break;
        }
      }
    }
    else {
      $result = $this->required_key($sig_keys);
    }
    return $result;
  }

  /**
   * @param $key
   * @param string $type
   * @return bool
   */
  private function required_key($key, $type = 'sig') {
    if (is_string($key) && strlen($key)) {
      $response = TRUE;
      switch ($type) {
        case 'sig':
          if (empty($this->vars['signature'][$key])) {
            SQRL::get_message()->log(Message::LOG_LEVEL_ERROR, 'Required sig @key missing', array('@key' => $key));
            $response = FALSE;
          }
          break;

        case 'pub':
          if (empty($this->vars['client'][$key])) {
            SQRL::get_message()->log(Message::LOG_LEVEL_ERROR, 'Required pk @key missing', array('@key' => $key));
            $response = FALSE;
          }
          break;

      }
    }
    else {
      $response = FALSE;
      SQRL::get_message()->log(Message::LOG_LEVEL_ERROR, 'Bad call to required_key');
    }
    return $response;
  }

  /**
   * list all signatures present
   *
   * @return array
   */
  public function signatures() {
    $sig_keys = array();
    foreach ($this->vars['signatures'] as $key => $sig) {
      if (!empty($sig)) {
        $sig_keys[] = $key;
      }
    }
    return $sig_keys;
  }

  /**
   * Are required signatures Present.
   *
   * @param $sig_key
   * @param $pub_key
   * @return bool False if any validation fales or true otherwise
   */
  public function validate_signature($sig_key, $pub_key) {
    //get signature
    if (!$this->required_key($sig_key, 'sig')) {
      return FALSE;
    }
    $sig = $this->vars['signatures'][$sig_key];
    //get related pk
    if (!$this->required_key($pub_key, 'pub')) {
      return FALSE;
    }
    $pk = $this->vars['client'][$pub_key];
    //validate
    return $this->ed25519_checkvalid($sig, $pk);
  }

  // TODO: Check the header values.
  public function check_header_values() {

  }

  // TODO: Check the client values.
  public function check_client_values() {

  }

  // TODO: Check the server values.
  public function check_server_values() {

  }

  // TODO: Validate nut
  public function validate_nut() {

  }

  // TODO: Validate same IP policy
  public function validate_same_ip() {

  }

  /**
   * @param $s
   * @param $m
   * @param $pk
   * @return bool
   * @throws Exception
   */
  public function validate($s, $m, $pk) {
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
   * @param $param
   * @param $key
   * @return array
   */
  private function decode_parameter($param, $key) {
    $string = $this->base64_decode($param);
    $values = explode(self::CRLF, $string);
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

}

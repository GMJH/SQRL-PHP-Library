<?php
/**
 * @author jurgenhaas
 */

namespace GMJH\SQRL\Sample;

use GMJH\SQRL\ClientException;

/**
 * An override class for Sample SQRL account
 *
 * @author jurgenhaas
 *
 * @link
 */
class Account extends \GMJH\SQRL\Account {

  #region Command ==============================================================

  /**
   * @param Client $client
   * @param bool $additional
   * @return bool
   * @throws ClientException
   */
  public function command_setkey($client, $additional = FALSE) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_setlock($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_disable($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_enable($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_delete($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_login($client) {
    return TRUE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_logme($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   * @throws ClientException
   */
  public function command_logoff($client) {
    return FALSE;
  }

  #endregion
}

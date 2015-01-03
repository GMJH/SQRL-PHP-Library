<?php
/**
 * @author jurgenhaas
 */

namespace JurgenhaasRamriot\SQRL\Sample;

/**
 * An override class for Sample SQRL account
 *
 * @author jurgenhaas
 *
 * @link
 */
class Account extends \JurgenhaasRamriot\SQRL\Account {

  #region Command ==============================================================

  /**
   * @param Client $client
   * @return bool
   */
  public function command_setkey_link($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_setkey($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_setlock($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_disable($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_enable($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_delete($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_login($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_logme($client) {
    return FALSE;
  }

  /**
   * @param Client $client
   * @return bool
   */
  public function command_logoff($client) {
    return FALSE;
  }

  #endregion
}

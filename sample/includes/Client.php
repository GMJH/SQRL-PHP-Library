<?php
/**
 * @author jurgenhaas
 */

namespace JurgenhaasRamriot\SQRL\Sample;

use JurgenhaasRamriot\SQRL\ClientException;

/**
 * An override class for Sample SQRL client operations
 *
 * @author jurgenhaas
 *
 * @link
 */
class Client extends \JurgenhaasRamriot\SQRL\Client {

  public function site_name() {
    return 'This is a sample site';
  }

  private function get_filename() {
    return sys_get_temp_dir() . '/sqrl.' . $this->sqrl->get_nut() . '.rsp';
  }

  protected function save($value) {
    file_put_contents($this->get_filename(), $value);
  }

  protected function load() {
    $filename = $this->get_filename();
    if (file_exists($filename)) {
      $result = file_get_contents($filename);
      file_delete($filename);
      return $result;
    }
    return FALSE;
  }

  /**
   * @param $key
   * @return Account
   */
  protected function find_user_account($key) {
    $uid = 1;
    return empty($uid) ? FALSE : new Account($uid);
  }

  #region Commands =============================================================

  /**
   * @return bool
   * @throws ClientException
   */
  protected function command_create() {
    if ($this->account) {
      // We can't create a new account if we found a matching one before.
      throw new ClientException('Can not create an account when a known account is provided');
    }
    $this->account = new Account(1);
    $this->set_message('Successfully created user account', self::FLAG_IDK_MATCH);
    $this->account->command_login($this);
    return TRUE;
  }

  #endregion

}

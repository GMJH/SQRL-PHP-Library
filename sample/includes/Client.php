<?php
/**
 * @author jurgenhaas
 */

namespace GMJH\SQRL\Sample;

/**
 * An override class for Sample SQRL client operations
 *
 * @author jurgenhaas
 *
 * @link
 */
class Client extends \GMJH\SQRL\Client {

  /**
   * @return string
   */
  public function site_name() {
    return 'This is a sample site';
  }

  /**
   * @return string
   */
  private function get_filename() {
    return sys_get_temp_dir() . '/sqrl.' . $this->sqrl->get_nut() . '.rsp';
  }

  /**
   * @param string $value
   */
  protected function save($value) {
    file_put_contents($this->get_filename(), $value);
  }

  /**
   * @return bool|string
   */
  protected function load() {
    $filename = $this->get_filename();
    if (file_exists($filename)) {
      $result = file_get_contents($filename);
      unlink($filename);
      return $result;
    }
    return FALSE;
  }

  /**
   * @param string $key
   * @return Account
   */
  protected function find_user_account($key) {
    $uid = 1;
    return empty($uid) ? FALSE : new Account($uid);
  }

}

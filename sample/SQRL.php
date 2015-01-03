<?php
/**
 * @author jurgenhaas
 */

namespace JurgenhaasRamriot\SQRL\Sample;

/**
 *
 */
class SQRL extends \JurgenhaasRamriot\SQRL\SQRL {

  const PATH_PREFIX = '';
  const PATH_CLIENT = 'auth.php';

  #region Private ==============================================================

  private function get_filename($mode) {
    return sys_get_temp_dir() . '/sqrl.' . $this->get_nut() . '.' . $mode;
  }

  #endregion

  #region Abstract implementation ==============================================

  protected function build_base_url() {
    global $base_url;
    $scheme = file_uri_scheme($base_url);
    $sqrl_base_url = $scheme ? substr($base_url, strlen($scheme) + 3) : $base_url;
    return $sqrl_base_url;
  }

  public function is_secure_connection_available() {
    return $GLOBALS['is_https'];
  }

  public function encrypt($data, $is_cookie) {
    return $data;
  }

  public function decrypt($data, $is_cookie) {
    return $data;
  }

  public function save($params) {
    file_put_contents($this->get_filename('nut'), serialize($params));
  }

  public function load() {
    $filename = $this->get_filename('nut');
    if (file_exists($filename)) {
      return unserialize(file_get_contents($filename));
    }
    return FALSE;
  }

  public function counter() {
    return mt_rand(0, 9999999);
  }

  /**
   * @param Account $account
   */
  public function authenticate($account) {
    $uid = 1;
    file_put_contents($this->get_filename('auth'), $uid);
  }

  protected function authenticated() {
    $filename = $this->get_filename('auth');
    if (file_exists($filename)) {
      $uid = file_get_contents($filename);
      file_delete($filename);
      return TRUE;
    }
    return FALSE;
  }

  #endregion

}

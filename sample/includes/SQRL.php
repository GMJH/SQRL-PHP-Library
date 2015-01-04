<?php
/**
 * @author jurgenhaas
 */

namespace JurgenhaasRamriot\SQRL\Sample;

/**
 *
 */
class SQRL extends \JurgenhaasRamriot\SQRL\SQRL {

  const PATH_PREFIX   = '';
  const PATH_CLIENT   = 'auth.php';
  const PATH_AJAX     = 'ajax.php?operation=';
  const PATH_VIEW     = 'view.php?operation=';
  const PATH_USER     = 'user.php';
  const PATH_QR_IMAGE = 'image.php';

  #region Private ==============================================================

  private function get_filename($mode) {
    return sys_get_temp_dir() . '/sqrl.' . $this->get_nut() . '.' . $mode;
  }

  #endregion

  #region Abstract implementation ==============================================

  protected function build_base_url() {
    $host = $this->get_server_value('HTTP_HOST');
    $script = $this->get_server_value('SCRIPT_NAME');
    $path = substr($script, 0, strrpos($script, '/'));
    return $host . $path;
  }

  public function is_secure_connection_available() {
    return FALSE;
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
      unlink($filename);
      return TRUE;
    }
    return FALSE;
  }

  #endregion

}

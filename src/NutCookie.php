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
 *
 */
final class NutCookie extends Nut {

  const IS_COOKIE  = TRUE;

  private $url_nut;

  /**
   * @param Nut $url_nut
   */
  public function set_url_nut($url_nut) {
    $this->url_nut = $url_nut;
  }

  /**
   *
   */
  public function set_cookie() {
    setcookie('sqrl', $this->nut_public, $this->get_timeout(), $this->wrapper->get_base_path(), $this->wrapper->get_domain());
  }

  /**
   *
   */
  public function build() {
    $this->nut_raw = $this->url_nut->nut_raw;
    $this->nut_encoded = $this->url_nut->nut_encoded;
    $this->encrypt();
    $this->set_cookie();
  }

  /**
   * @throws NutException
   */
  public function fetch() {
    parent::fetch();
    $this->is_match_encoded_nuts();
    $this->is_match_raw_nuts();
  }

  /**
   * @return string
   * @throws NutException
   */
  protected function fetch_nut() {
    $nut = isset($_COOKIE['sqrl']) ? $_COOKIE['sqrl'] : '';
    if (!$nut) {
      throw new NutException('Nut missing from COOKIE request');
    }
    return $nut;
  }

  /**
   * @throws NutException
   */
  private function is_match_raw_nuts() {
    foreach ($this->nut_raw as $key => $value) {
      if ($this->url_nut->nut_raw[$key] != $value) {
        throw new NutException('Nut in url and cookie raw parameter arrays do not match');
      }
    }
  }

  /**
   * @throws NutException
   */
  private function is_match_encoded_nuts() {
    $str_url = $this->url_nut->nut_encoded;
    $str_cookie = $this->nut_encoded;
    if (!$this->time_safe_strcomp($str_url, $str_cookie)) {
      throw new NutException('Nut in url and cookie encoded strings do not match');
    }
  }

  /**
   * @param $str1
   * @param $str2
   * @return bool
   */
  private function time_safe_strcomp($str1, $str2) {
    $str1_len = strlen($str1);
    $str2_len = strlen($str2);
    if ($str1_len == 0 || $str2_len == 0) {
      throw new \InvalidArgumentException('This function cannot safely compare against an empty given string');
    }
    $res = $str1_len ^ $str2_len;
    for ($i = 0; $i < $str1_len; ++$i) {
      $res |= ord($str1[$i % $str1_len]) ^ ord($str2[$i]);
    }
    if ($res === 0) {
      return TRUE;
    }
    return FALSE;
  }

}

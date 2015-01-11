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

define('SQRL_LOG_LEVEL_ERROR', 1);
define('SQRL_LOG_LEVEL_WARNING', 2);
define('SQRL_LOG_LEVEL_INFO', 3);
define('SQRL_LOG_LEVEL_DEBUG', 4);

/**
 *
 */
final class Message {

  private $callback_log;
  private $callback_msg;

  /**
   *
   */
  public function __construct() {
    // Nothing to do here but we have to have the constructor to avoid
    // warnings in some PHP versions.
  }

  /**
   * @param string $type
   *  Either 'log' or 'msg'
   * @param string $callback
   *  Function name of the callback to be used for the given $type
   */
  public function register_callback($type, $callback) {
    switch ($type) {
      case 'log':
        $this->callback_log = $callback;
        break;

      case 'msg':
        $this->callback_msg = $callback;
        break;

    }
  }

  /**
   * @param int $severity
   * @param string $message
   * @param array $variables
   */
  public function log($severity, $message, $variables = array()) {
    if (!empty($this->callback_log) && function_exists($this->callback_log)) {
      if ($severity == SQRL_LOG_LEVEL_DEBUG) {
        $variables += array(
          'sqrl-post' => $_POST,
          'sqrl-get' => $_GET,
          'sqrl-cookie' => $_COOKIE,
        );
      }
      $this->sanitize($variables);
      call_user_func($this->callback_log, $severity, $message, $variables);
    }
  }

  /**
   * @param string $type
   * @param string $message
   * @param array $variables
   */
  public function msg($type, $message, $variables = array()) {
    if (!empty($this->callback_msg) && function_exists($this->callback_msg)) {
      $this->sanitize($variables);
      call_user_func($this->callback_msg, $type, $message, $variables);
    }
  }

  /**
   * helper function to do token replacement in strings
   *
   * @param string $string
   * @param array $args
   * @return string
   */
  public function format($string, array $args = array()) {
    // Transform arguments before inserting them.
    foreach ($args as $key => $value) {
      switch ($key[0]) {
        case '@':
          // Escaped only.
          $args[$key] = $this->check_plain($value);
          break;

        case '!':
          // Pass-through.
          break;

        case '%':
        default:
          // Escaped and placeholder.
          $args[$key] = $this->placeholder($value);
          break;

      }
    }
    return strtr($string, $args);
  }

  /**
   * @param string $text
   * @return string
   */
  private function check_plain($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  /**
   * @param string $text
   * @return string
   */
  private function placeholder($text) {
    return '<em class="placeholder">' . $this->check_plain($text) . '</em>';
  }

  /**
   * @param array $variables
   */
  private function sanitize(&$variables) {
    foreach ($variables as $key => $value) {
      if (!is_scalar($value)) {
        if (method_exists($value, 'toDebug')) {
          $variables[$key] = $value->toDebug();
        }
        else {
          $variables[$key] = json_encode($value);
        }
      }
    }
  }

}

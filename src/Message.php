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
class Message {

  const LOG_LEVEL_ERROR = 1;
  const LOG_LEVEL_WARNING = 2;
  const LOG_LEVEL_INFO = 3;

  private $callback_log;
  private $callback_message;

  /**
   * @param $type
   *  Either 'log' or 'message'
   * @param $callback
   *  Function name of the callback to be used for the given $type
   */
  public function register_callback($type, $callback) {
    switch ($type) {
      case 'log':
        $this->callback_log = $callback;
        break;

      case 'message':
        $this->callback_message = $callback;
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
      $this->sanitize($variables);
      call_user_func($this->callback_log, $severity, $message, $variables);
    }
  }

  /**
   * @param string $type
   * @param string $message
   * @param array $variables
   */
  public function message($type, $message, $variables = array()) {
    if (!empty($this->callback_message) && function_exists($this->callback_message)) {
      $this->sanitize($variables);
      call_user_func($this->callback_message, $type, $message, $variables);
    }
  }

  /**
   * helper function to do token replacement in strings
   *
   * @param $string
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
   * @param $text
   * @return string
   */
  private function check_plain($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  /**
   * @param $text
   * @return string
   */
  private function placeholder($text) {
    return '<em class="placeholder">' . $this->check_plain($text) . '</em>';
  }

  private function sanitize(&$variables) {
    foreach ($variables as $key => $value) {
      if (!is_scalar($value)) {
        $variables[$key] = print_r($value, TRUE);
      }
    }
  }

}

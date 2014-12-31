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
 * This class instantiates a class as a singleton
 * to keep only one instance present for a whole request cycle,
 * This is a better option than dependency injection.
 * NB: Implementers on other platforms can place their own override
 * classes and use the class name as the call instead of any default.
 */
final class SQRL {

  private static $instance = array();

  //The constructor is private so that outside code cannot instantiate
  private function __construct() {}

  //Just in case block the clone method also
  private function __clone() {}

  //Serialization prevention - enable this after debugging
  /*
  public function __wakeup() {
    throw new Exception("Cannot unserialize singleton");
  }
  // */

  //Instantiate singleton instance
  private static function get($classname) {
    if (!isset(self::$instance[$classname])) {
      switch ($classname) {
        case 'nut':
          $full_classname = '\JurgenhaasRamriot\SQRL\Nut';
          break;

        case 'client':
          $full_classname = '\JurgenhaasRamriot\SQRL\Client';
          break;

        case 'message':
          $full_classname = '\JurgenhaasRamriot\SQRL\Message';
          break;

        default:
          return NULL;

      }
      self::$instance[$classname] = new $full_classname;
    }
    return self::$instance[$classname];
  }

  /**
   * @return \JurgenhaasRamriot\SQRL\Nut
   */
  public static function get_nut() {
    return self::get('nut');
  }

  /**
   * @return \JurgenhaasRamriot\SQRL\Client
   */
  public static function get_client() {
    return self::get('client');
  }

  /**
   * @return \JurgenhaasRamriot\SQRL\Message
   */
  public static function get_message() {
    return self::get('message');
  }

  //Debugging method to Innumerate active singletons
  public static function innumerate() {
    return array_keys(self::$instance);
  }

}

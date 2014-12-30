<?php
/**
 * @author ramriot
 */

/**
 * This class instantiates a class as a singleton
 * to keep only one instance present for a whole request cycle,
 * This is a better option than dependency injection.
 * NB: Implementers on other platforms can place their own override
 * classes and use the class name as the call instead of any default.
 * @'method singleton get_instance($classname)
 */
final class sqrl_instance {
  private static $instance = array();

  //The constructor is private so that outside code cannot instantiate
  private function __construct() {
  }

  //Just in case block the clone method also
  private function __clone() {
  }
  //Serialization prevention - enable this after debugging
  /*
  public function __wakeup()
  {
      throw new Exception("Cannot unserialize singleton");
  }
  // */
  //Instantiate singleton instance
  public static function get_instance($classname) {
    if (!isset(self::$instance[$classname])) {
      //self::$instances[$classname] = new static;
      self::$instance[$classname] = new $classname;
    }
    return self::$instance[$classname];
  }

  //Debugging method to Innumerate active singletons
  public static function innumerate() {
    return array_keys(self::$instance);
  }
}

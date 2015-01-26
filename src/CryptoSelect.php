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

/**
 * Proxy for family of Crypto classes
 */
class CryptoSelect {

  public function __construct(Crypto $crypto=NULL) {
    // Do something to initialize this.
    $this->crypto = $crypto?$crypto:$this->select();
  }

  /**
   * Magic method fired when a method not in class is called
   * This will pass on all those to the proxied crypto class
   */
  function __call($method, $args) {
    // Run before code here
    SQRL::get_message()->log(SQRL_LOG_LEVEL_DEBUG, 'Proxy Method: ' . $method . " class=>" . get_class($this->crypto));
    // Invoke original method on our proxied object
    return call_user_func_array(array($this->crypto, $method), $args);

    // Run after code here
  }
  
  /**
   * Static function to return a list of all crypto library extensions
   * included with this version
   */
  static function cryptoList() {
    //return list of Ed25519 classes in priority order
    return array(
      'CryptoPhpExtSqrl',
      'CryptoLibsodiumPhp',
      'CryptoPhp',
    );
  }
  
  private function select()  {
    //loop through list in order and return instantiated class of first supported.
    foreach($this->cryptoList() as $className) {
      //instantiate crypto (Does namespace need to be added?)
      $fullname = __NAMESPACE__ . '\\' . $className;
      $class = new $fullname;
      if($class->supported())
      {
        return $class;
      }
    }
  }
}

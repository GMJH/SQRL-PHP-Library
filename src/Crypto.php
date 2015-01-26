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

interface CryptoInterface {
  //Static method to return a description of extension
    static function description();
  //Static method to return TRUE if extension supported
    static function supported();
}
/**
 *  Crypto base class to guide required methods, also extends Common to
 *  allow use of its helper methods.
 */
abstract class Crypto extends Common implements CryptoInterface {

/*
  public function __construct() {
    // Do something to initialize this.
  }
*/
  
  /**
   * This abstract method must be overriden
   * Take in predefined array for signature validation
   * and process to format required for validation extension
   * @param $box : object of type CryptoBox
   * @exception: of type CryptoException
   *
   */
  abstract public function process($box);

  /**
   * This abstract method must be overriden
   * Perform validation against $this->box
   * @return: Boolean True for valid signature
   * @exception: of type CryptoException
   */
  abstract public function validate();
  
}

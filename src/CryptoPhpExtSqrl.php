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
 * https://github.com/ramriot/php-ext-sqrl
 */
class CryptoPhpExtSqrl extends Crypto {
    private $msg, $sig, $pk;

    /**
     *  @return String: Description of library the derived class supports
     */
    static function description()   {
        $ret =  "PHP Ed25519 signature validation using extension https://github.com/ramriot/php-ext-sqrl";
        return $ret;
    }
  
    /**
     * Construct needs to include input formatters to take the
     * client parameters and modify them for the validation stage
     */
    static function supported()   {
        //Detect presence of library and load at runtime if not already
        if (!extension_loaded('sqrl')) {
            dl('sqrl.so');
        }
        if(!function_exists('sqrl_verify'))    {
            return FALSE;
        }
        return TRUE;
    }
    
    public function process($box)   {
        $this->msg  = $box['message'];
        $this->sig  = $box['signature'];
        $this->pk   = $box['publickey'];
    }
    
    public function validate() {
        return sqrl_verify( $this->msg, $this->sig, $this->pk );
    }
}

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
 * https://github.com/jedisct1/libsodium-php
 */
class CryptoLibsodiumPhp extends Crypto {
    private $signed_msg, $public_key;

    /**
     *  @return String: Description of library the derived class supports
     */
    static function description()   {
        $ret =  "PHP Ed25519 signature validation using extension https://github.com/jedisct1/libsodium-php";
        return $ret;
    }
  
    /**
     * Construct needs to include input formatters to take the
     * client parameters and modify them for the validation stage
     */
    static function supported()   {
        //Detect presence of library and load at runtime if not already
        if (!extension_loaded('libsodium')) {
            dl('libsodium.so');
        }
        if(!function_exists('crypto_sign_open'))    {
            return FALSE;
        }
        return TRUE;
    }
    
    public function process($box)   {
        //need to process signature and message into one argument
        $this->signed_msg = Common::base64_decode($box['signature']) . $box['message'];
        //public as normal but perhaps need to convert type
        $this->public_key = Common::base64_decode($box['publickey']);
    }
    
    public function validate() {
        $msg_orig = crypto_sign_open($this->signed_msg, $this->public_key);
        return ($msg_orig === FALSE)?FALSE:TRUE;
    }


}

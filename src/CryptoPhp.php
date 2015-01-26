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
 *  Crypto Class for native PHP Ed25519 signature validation (SLOW!)
 *  https://github.com/trianglman/sqrl
 *  NB: The validation method here is very slow and should not be
 *  used in production, see README for other supported PHP extensions
 */
class CryptoPhp extends Crypto {
    private $msg, $sig, $pk;

    /**
     *  @return String: Description of library the derived class supports
     */
    static function description()   {
        $ret =  "Native PHP Ed25519 signature validation using part of library from https://github.com/trianglman/sqrl";
        return $ret;
    }
      
    /**
     * Construct needs to include input formatters to take the
     * client parameters and modify them for the validation stage
     */
    static function supported()   {
        //Detect presence of library - In this case native PHP
        return TRUE;
    }
    
    public function process($box)   {
        //I think here that the checkvalid function expects:-
        //msg string direct
        $this->msg  = $box['message'];
        //sig > binary string
        $this->sig  = Common::base64_decode($box['signature']);
        //pk > binary string
        $this->pk   = Common::base64_decode($box['publickey']);
    }
    
    public function validate() {
        //TBD Enable when more efficiently coded.
        return TRUE;//$this->checkvalid($this->sig, $this->msg, $this->pk);
    }
    
    //BEGIN Crypto section taken from https://github.com/trianglman/sqrl/blob/master/src/Trianglman/Sqrl/Ed25519/Crypto.php
    protected $b;
    protected $q;
    protected $l;
    protected $d;
    protected $I;
    protected $By;
    protected $Bx;
    protected $B;
    public function __construct()
    {
        $this->b = 256;
        $this->q = "57896044618658097711785492504343953926634992332820282019728792003956564819949"; //bcsub(bcpow(2, 255),19);
        $this->l = "7237005577332262213973186563042994240857116359379907606001950938285454250989"; //bcadd(bcpow(2,252),27742317777372353535851937790883648493);
        $this->d = "-4513249062541557337682894930092624173785641285191125241628941591882900924598840740"; //bcmul(-121665,$this->inv(121666));
        $this->I = "19681161376707505956807079304988542015446066515923890162744021073123829784752"; //$this->expmod(2,  bcdiv((bcsub($this->q,1)),4),$this->q);
        $this->By = "46316835694926478169428394003475163141307993866256225615783033603165251855960"; //bcmul(4,$this->inv(5));
        $this->Bx = "15112221349535400772501151409588531511454012693041857206046113283949847762202"; //$this->xrecover($this->By);
        $this->B = array(
            "15112221349535400772501151409588531511454012693041857206046113283949847762202",
            "46316835694926478169428394003475163141307993866256225615783033603165251855960"
        ); //array(bcmod($this->Bx,$this->q),bcmod($this->By,$this->q));
    }
    protected function H($m)
    {
        return hash('sha512', $m, true);
    }
    //((n % M) + M) % M //python modulus craziness
    protected function pymod($x, $m)
    {
        $mod = bcmod($x, $m);
        if ($mod[0] === '-') {
            $mod = bcadd($mod, $m);
        }
        return $mod;
    }
    protected function expmod($b, $e, $m)
    {
        //if($e==0){return 1;}
        $t = bcpowmod($b, $e, $m);
        if ($t[0] === '-') {
            $t = bcadd($t, $m);
        }
        return $t;
    }
    protected function inv($x)
    {
        return $this->expmod($x, bcsub($this->q, 2), $this->q);
    }
    protected function xrecover($y)
    {
        $y2 = bcpow($y, 2);
        $xx = bcmul(bcsub($y2, 1), $this->inv(bcadd(bcmul($this->d, $y2), 1)));
        $x = $this->expmod($xx, bcdiv(bcadd($this->q, 3), 8, 0), $this->q);
        if ($this->pymod(bcsub(bcpow($x, 2), $xx), $this->q) != 0) {
            $x = $this->pymod(bcmul($x, $this->I), $this->q);
        }
        if (substr($x, -1)%2 != 0) {
            $x = bcsub($this->q, $x);
        }
        return $x;
    }
    protected function edwards($P, $Q)
    {
        list($x1, $y1) = $P;
        list($x2, $y2) = $Q;
        $xmul = bcmul($x1, $x2);
        $ymul = bcmul($y1, $y2);
        $com = bcmul($this->d, bcmul($xmul, $ymul));
        $x3 = bcmul(bcadd(bcmul($x1, $y2), bcmul($x2, $y1)), $this->inv(bcadd(1, $com)));
        $y3 = bcmul(bcadd($ymul, $xmul), $this->inv(bcsub(1, $com)));
        return array($this->pymod($x3, $this->q), $this->pymod($y3, $this->q));
    }
    protected function scalarmult($P, $e)
    {
        if ($e == 0) {
            return array(0, 1);
        }
        $Q = $this->scalarmult($P, bcdiv($e, 2, 0));
        $Q = $this->edwards($Q, $Q);
        if (substr($e, -1)%2 == 1) {
            $Q = $this->edwards($Q, $P);
        }
        return $Q;
    }
    protected function scalarloop($P, $e)
    {
        $temp = array();
        $loopE = $e;
        while ($loopE > 0) {
            array_unshift($temp, $loopE);
            $loopE = bcdiv($loopE, 2, 0);
        }
        $Q = array();
        foreach ($temp as $e) {
            if ($e == 1) {
                $Q = $this->edwards(array(0, 1), $P);
            } elseif (substr($e, -1)%2 == 1) {
                $Q = $this->edwards($this->edwards($Q, $Q), $P);
            } else {
                $Q = $this->edwards($Q, $Q);
            }
        }
        return $Q;
    }
    protected function bitsToString($bits)
    {
        $string = '';
        for ($i = 0; $i < $this->b/8; $i++) {
            $sum = 0;
            for ($j = 0; $j < 8; $j++) {
                $bit = $bits[$i*8+$j];
                $sum += (int) $bit << $j;
            }
            $string .= chr($sum);
        }
        return $string;
    }
    protected function dec2bin_i($decimal_i)
    {
        $binary_i = '';
        do {
            $binary_i = substr($decimal_i, -1)%2 .$binary_i;
            $decimal_i = bcdiv($decimal_i, '2', 0);
        } while (bccomp($decimal_i, '0'));
        return ($binary_i);
    }
    protected function encodeint($y)
    {
        $bits = substr(str_pad(strrev($this->dec2bin_i($y)), $this->b, '0', STR_PAD_RIGHT), 0, $this->b);
        return $this->bitsToString($bits);
    }
    protected function encodepoint($P)
    {
        list($x, $y) = $P;
        $bits = substr(str_pad(strrev($this->dec2bin_i($y)), $this->b-1, '0', STR_PAD_RIGHT), 0, $this->b-1);
        $bits .= (substr($x, -1)%2 == 1 ? '1' : '0');
        return $this->bitsToString($bits);
    }
    protected function bit($h, $i)
    {
        return (ord($h[(int) bcdiv($i, 8, 0)]) >> substr($i, -3)%8) & 1;
    }
    /**
     * Generates the public key of a given private key
     *
     * @param string $sk the secret key
     *
     * @return string
     */
    public function publickey($sk)
    {
        $h = $this->H($sk);
        $sum = 0;
        for ($i = 3; $i < $this->b-2; $i++) {
            $sum = bcadd($sum, bcmul(bcpow(2, $i), $this->bit($h, $i)));
        }
        $a = bcadd(bcpow(2, $this->b-2), $sum);
        $A = $this->scalarmult($this->B, $a);
        $data = $this->encodepoint($A);
        return $data;
    }
    protected function Hint($m)
    {
        $h = $this->H($m);
        $sum = 0;
        for ($i = 0; $i < $this->b*2; $i++) {
            $sum = bcadd($sum, bcmul(bcpow(2, $i), $this->bit($h, $i)));
        }
        return $sum;
    }
    public function signature($m, $sk, $pk)
    {
        $h = $this->H($sk);
        $a = bcpow(2, (bcsub($this->b, 2)));
        for ($i = 3; $i < $this->b-2; $i++) {
            $a = bcadd($a, bcmul(bcpow(2, $i), $this->bit($h, $i)));
        }
        $r = $this->Hint(substr($h, $this->b/8, ($this->b/4-$this->b/8)).$m);
        $R = $this->scalarmult($this->B, $r);
        $encR = $this->encodepoint($R);
        $S = $this->pymod(bcadd($r, bcmul($this->Hint($encR.$pk.$m), $a)), $this->l);
        return $encR.$this->encodeint($S);
    }
    protected function isoncurve($P)
    {
        list($x, $y) = $P;
        $x2 = bcpow($x, 2);
        $y2 = bcpow($y, 2);
        return $this->pymod(bcsub(bcsub(bcsub($y2, $x2), 1), bcmul($this->d, bcmul($x2, $y2))), $this->q) == 0;
    }
    protected function decodeint($s)
    {
        $sum = 0;
        for ($i = 0; $i < $this->b; $i++) {
            $sum = bcadd($sum, bcmul(bcpow(2, $i), $this->bit($s, $i)));
        }
        return $sum;
    }
    /*
     * def decodepoint(s):
      y = sum(2**i * bit(s,i) for i in range(0,b-1))
      x = xrecover(y)
      if x & 1 != bit(s,b-1): x = q-x
      P = [x,y]
      if not isoncurve(P): raise Exception("decoding point that is not on curve")
      return P
     */
    protected function decodepoint($s)
    {
        $y = 0;
        for ($i = 0; $i < $this->b-1; $i++) {
            $y = bcadd($y, bcmul(bcpow(2, $i), $this->bit($s, $i)));
        }
        $x = $this->xrecover($y);
        if (substr($x, -1)%2 != $this->bit($s, $this->b-1)) {
            $x = bcsub($this->q, $x);
        }
        $P = array($x, $y);
        if (!$this->isoncurve($P)) {
            throw new \Exception("Decoding point that is not on curve");
        }
        return $P;
    }
    public function checkvalid($s, $m, $pk)
    {
        if (strlen($s) != $this->b/4) {
            throw new \Exception('Signature length is wrong');
        }
        if (strlen($pk) != $this->b/8) {
            throw new \Exception('Public key length is wrong: '.strlen($pk));
        }
        $R = $this->decodepoint(substr($s, 0, $this->b/8));
        try {
            $A = $this->decodepoint($pk);
        } catch (\Exception $e) {
            return false;
        }
        $S = $this->decodeint(substr($s, $this->b/8, $this->b/4));
        $h = $this->Hint($this->encodepoint($R).$pk.$m);
        return $this->scalarmult($this->B, $S) == $this->edwards($R, $this->scalarmult($A, $h));
    }
    //End - Crypto section taken from https://github.com/trianglman/sqrl/blob/master/src/Trianglman/Sqrl/Ed25519/Crypto.php
}

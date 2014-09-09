<?php
/**
 * A common class for helper functions in assistance of SQRL nut and client operations
 *
 * @author ramriot
 */
class sqrl_common
{
    
    protected function _ip_to_long($ip)   {
        if (strlen($ip) > 15) {
            //for IPV6 output long from ast 8 bytes of sha1
            return hexdec(substr(hash('sha1', $ip, FALSE), -8));
        }
        else {
            return ip2long($ip);
        }
    }
    
    protected function _long_to_ip($ip)   {
        return long2ip((float)$ip);
    }

    protected function _bytes_extract($bytes, $start, $len) {
      $result = '';
      while ($len > 0) {
        $result .= $bytes[$start];
        $start++;
        $len--;
      }
      return $result;
    }
    
    /**
    * Get the value of a key in the $_SERVER array or the string unknown if the
    * key is not set.
    *
    * @param string $key
    * @return string
    */
    public function _get_server_value($key) {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : 'unknown';
    }

    public function _decode_parameter($param)
    {
        $string = _sqrl_client_base64_decode($param);
        $values = explode(SQRL_CRLF, $string);
        foreach ($values as $value) {
          if (!empty($value)) {
            $parts = explode('=', $value);
            $k = array_shift($parts);
            $vars[$key][$k] = implode('=', $parts);
          }
        }
        return $vars;
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

    protected function bit($h, $i)
    {
        return (ord($h[(int) bcdiv($i, 8, 0)]) >> substr($i, -3)%8) & 1;
    }

    protected function decodeint($s)
    {
        $sum = 0;
        for ($i = 0; $i < $this->b; $i++) {
            $sum = bcadd($sum, bcmul(bcpow(2, $i), $this->bit($s, $i)));
        }

        return $sum;
    }
    
    protected function time_safe_strcomp($str1, $str2)
    {
        $str_url    = $this->encoded['url'];
        $str_cookie = $this->encoded['cookie'];
        if (strlen($str1) == 0 || strlen($str2) == 0) {
            throw new InvalidArgumentException("This function cannot safely compare against an empty given string");
        }
        $res = strlen($str1) ^ strlen($str2);
        $str1_len = strlen($str1);
        $str2_len = strlen($str2);
        for ($i = 0; $i < $str1_len; ++$i) {
            $res |= ord($str1[$i % $str1_len]) ^ ord($str2[$i]);
        }
        if($res === 0)   {
            return TRUE;
        }
        return FALSE;
    }
}

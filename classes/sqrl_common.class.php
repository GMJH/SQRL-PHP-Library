<?php
/**
 * A common class for helper functions in assistance of SQRL nut and client operations
 *
 * @author ramriot
 */
abstract class sqrl_common
{
    /**helper function to fetch the client ip address allowing for
     * several possible server network configurations
     * @return IPv4 address
     */
    protected function _get_ip_address() {
        foreach (array('HTTP_CLIENT_IP',
                       'HTTP_X_FORWARDED_FOR',
                       'HTTP_X_FORWARDED',
                       'HTTP_X_CLUSTER_CLIENT_IP',
                       'HTTP_FORWARDED_FOR',
                       'HTTP_FORWARDED',
                       'REMOTE_ADDR') as $key){
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $IPaddress){
                    $IPaddress = trim($IPaddress); // Just to be safe
    
                    if (filter_var($IPaddress,
                                   FILTER_VALIDATE_IP,
                                   FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                        !== false) {
    
                        return $IPaddress;
                    }
                }
            }
        }
    }
    
    /**
     * function got fetch a series of high entropy psudo random bytes
     * @param int: length in bytes for returned random
     * @return binary string of length $count bytes
     */
    protected function _get_random_bytes($count)   {
        static $random_state, $bytes, $has_openssl;        
        $missing_bytes = $count - strlen($bytes);
        if ($missing_bytes > 0) {
            // PHP versions prior 5.3.4 experienced openssl_random_pseudo_bytes()
            // locking on Windows and rendered it unusable.
            if (!isset($has_openssl)) {
                $has_openssl = version_compare(PHP_VERSION, '5.3.4', '>=') && function_exists('openssl_random_pseudo_bytes');
            }
            // openssl_random_pseudo_bytes() will find entropy in a system-dependent
            // way.
            if ($has_openssl) {
                $bytes .= openssl_random_pseudo_bytes($missing_bytes);
            }
            // Else, read directly from /dev/urandom, which is available on many *nix
            // systems and is considered cryptographically secure.
            elseif ($fh = @fopen('/dev/urandom', 'rb')) {
                // PHP only performs buffered reads, so in reality it will always read
                // at least 4096 bytes. Thus, it costs nothing extra to read and store
                // that much so as to speed any additional invocations.
                $bytes .= fread($fh, max(4096, $missing_bytes));
                fclose($fh);
            }
            // If we couldn't get enough entropy, this simple hash-based PRNG will
            // generate a good set of pseudo-random bytes on any system.
            // Note that it may be important that our $random_state is passed
            // through hash() prior to being rolled into $output, that the two hash()
            // invocations are different, and that the extra input into the first one -
            // the microtime() - is prepended rather than appended. This is to avoid
            // directly leaking $random_state via the $output stream, which could
            // allow for trivial prediction of further "random" numbers.
            if (strlen($bytes) < $count) {
                // Initialize on the first call. The contents of $_SERVER includes a mix of
                // user-specific and system information that varies a little with each page.
                if (!isset($random_state)) {
                    $random_state = print_r($_SERVER, TRUE);
                    if (function_exists('getmypid')) {
                      // Further initialize with the somewhat random PHP process ID.
                      $random_state .= getmypid();
                    }
                    $bytes = '';
                }
          
                do {
                    $random_state = hash('sha256', microtime() . mt_rand() . $random_state);
                    $bytes .= hash('sha256', mt_rand() . $random_state, TRUE);
                } while (strlen($bytes) < $count);
            }
        }
        $output = substr($bytes, 0, $count);
        $bytes = substr($bytes, $count);
        return $output;
    }
        
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

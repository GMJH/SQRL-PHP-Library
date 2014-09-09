<?php
/**
 * @author ramriot
 */

 /**
  * See end for useage examples
  */
 
/**
 * Interface to sqrl_nut class
 */
interface sqrl_nut_api {
    //compound functions
    function build();//Build a new nut object
    function fetch();//Fetch nut from client request and validate
    //get functions
    function get_nut($cookie);//Get nut string set arg to true for cookie
    function get_encoded($cookie);//Get encoded nut string set arg to true for cookie
    function get_status();//Get operation status
    function get_msgs();//Get any debugging messages
    function is_clean();//Test for clean object
    function is_error();//Is there an operational error present
    function get_time();//Get timestamp from nut
    function get_op_params();//get an array of all stored operational parameters 
    //set functions
    function build_nut();//Build raw nut from system parameters
    function encode($cookie);//Take raw nut/cookie and encode into string
    function decode($cookie);//Take encoded nut/cookie string and decode to params
    function encrypt($cookie);//Take encoded nut/cookie and encrypt
    function decrypt($cookie);//Take encrypted nut/cookie and decrypt
    function set_op_params($key, $value);//Store key value pairs for later offline storage and use
    //test functions
    function validate();//Decode nut and cookie and validate all parts
    function validate_encoded();//Validation of nut without decoding string
}

/**
 * ****
 * **** This class defines the main structure for SQRL nut building,
 * **** encoding, decoding and validation.
 * ****
 */
class sqrl_nut extends sqrl_common implements sqrl_nut_api {
    const NUT_LIFETIME      = 600;
    //error constants
    const NUT_EXISTS        = 1;
    const VALIDATION_FAILED = 2;
    //selector constants
    const SELECT_COOKIE     = 1;
    const SELECT_URL        = 0;
    //status constants
    const STATUS_VALID      = 'validated';
    const STATUS_INVALID    = 'invalid';
    const STATUS_BUILD      = 'built';
    const STATUS_FETCH      = 'fetched';
    const STATUS_NOCOOKIE   = 'nocookie';
    //internal variables
    protected $raw      =   array();//raw nut parameters assoc key url/cookie
    protected $encoded  =   array();//encoded nut strings assoc key url/cookie
    protected $nut      =   array();//encrypted nut strings assoc key url/cookie
    protected $params   =   array();//other parameters for storage in cache
    protected $error    =   FALSE;//Error flag
    protected $msg      =   array();//Error messages
    protected $clean    =   TRUE;//Clean object flag
    protected $status   =   '';//Current object status string
    protected $op       =   '';//Current opperation (??)
    
    public function build_nut()    {
        $this->raw['url'] = array(
            'time'      =>  $this->get_request_time(),
            'ip'        =>  $this->get_ip_address(),
            'counter'   =>  $this->get_counter(),// Third 32-bit: incremental counter
            'random'    =>  $this->get_random_bytes(4),// Fourth 32-bit: pseudo-random noise
        );
        //clone raw url data to raw cookie
        $this->raw['cookie']    =   $this->raw['url'];
        return $this;
    }
    
    protected function get_request_time()   {
        static $request_time = 0;
        if(!$request_time)  $request_time = (int) $_SERVER['REQUEST_TIME'];
        return $request_time;
    }
    
    protected function get_ip_address() {
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
    
    protected function get_counter()   {
        //this must be overriden by the implementor
        return FALSE;
    }
    
    protected function get_random_bytes($count)   {
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
    
    public function encode($cookie)    {
        $key = $cookie?'cookie':'url';
        $ref = & $this->raw[$key];
        //format bytes
        $output = pack('LLL', $ref['time'], $this->_ip_to_long($ref['ip']), $ref['counter']) . $ref['random'];
        $this->encoded[$key] = $output;
        return $this;
    }

    //decodes nut to original data, for validate this may not be needed as it could be enough to compare the byte strings.
    public function decode($cookie) {
        $key = $cookie?'cookie':'url';
        $ref = & $this->encoded;
        $this->raw[$key] = array(
          'time'    => $this->decode_time($ref[$key]),
          'ip'      => $this->decode_ip($ref[$key]),
          'counter' => $this->decode_counter($ref[$key]),
          'noise'   => $this->decode_random($ref[$key]),
        );
        return $this;
    }
    
    private function decode_time($bytes)   {
        $output = unpack('L', $this->_bytes_extract($bytes, 0, 4));
        return array_shift($output);
    }
    
    private function decode_ip($bytes)   {
        $output = unpack('L', $this->_bytes_extract($bytes, 4, 4));
        return $this->_long_to_ip(array_shift($output));
    }
    
    private function decode_counter($bytes)   {
        $output = unpack('L', $this->_bytes_extract($bytes, 8, 4));
        return array_shift($output);
    }
    
    private function decode_random($bytes)   {
        $output = $this->_bytes_extract($bytes, 12, 4);
        return $output;
    }
    
    public function encrypt($cookie) {
        //this must be overriden by the implementor
        //take $this->encoded => $this->nut
        return $this;
    }
    
    public function decrypt($cookie) {
        //this must be overriden by the implementor
        //take $this->nut => $this->encoded
        return $this;
    }
   
    public function set_cookie()   {
        setcookie('sqrl', $this->nut['cookie'], $this->get_request_time() + self::NUT_LIFETIME, '/', $this->get_base_url());
        return $this;
    }

    protected function get_base_url()   {
        //this must be overriden by the implementor
        return '';
    }
    
    protected function cache_set()    {
        //this must be overriden by the implementor
        return $this;
    }
    
    protected function cache_get()    {
        //this must be overriden by the implementor
        return $this;
    }

    public function validate()     {
        foreach ($this->raw['url'] as $key => $value) {
            if ($this->raw['cookie'][$key] != $value) {
                // Nuts don't match, so we reject this request too.
                $this->error = TRUE;
                $this->msg[] = array(self::VALIDATION_FAILED, '');//TBD add meaningful words here
                break;
            }
        }
        if(!$this->error)   {
            $this->status = self::STATUS_VALID;
            $this->cache_get();
            if (empty($this->params)) {
                $this->params['op'] = 'login';
            }
        }
        return $this;
    }
    
    public function validate_encoded()     {
        $str_url    = $this->encoded['url'];
        $str_cookie = $this->encoded['cookie'];
        $valid = $this->time_safe_strcomp($str_url, $str_cookie);
        if($valid)   {
            $this->status = self::STATUS_VALID;
            $this->cache_get();
            if (empty($this->params)) {
                $this->params['op'] = 'login';
            }
        } else {
            $this->error = TRUE;
            $this->msg[] = array(self::VALIDATION_FAILED, '');//TBD add meaningful words here
        }
        return $this;
    }
    /**
     * Setter for additional parameters that will get integrated into
     * the NUT. This is useful so that the request from the SQRL client will contain
     * those extra parameters as the server may need them to perform the required
     * operation associated with the SQRL request.
     *
     * @param string $key
     * @param string $value
     * @return object
     */
    public function set_op_params($key = NULL, $value = NULL) {
      if (isset($key) && isset($value)) {
        $this->params[$key] = $value;
      }
    /*
      else {
        return array(
          'op' => $this->get_op(),
          'params' => $this->params,
        );
      }
      */
      return $this;
    }

    /**
     * Getter for additional parameters that will get integrated into
     * the NUT. This is useful so that the request from the SQRL client will contain
     * those extra parameters as the server may need them to perform the required
     * operation associated with the SQRL request.
     *
     * @return array
     */
    public function get_op_params($key = null) {
        if(null == $key)    {
            return $this->params;
        }
        return $this->params[$key];
    }

    /**
     * Return encrypted nut defaults to URL or set $cookie to TRUE for cookie
     */
    public function get_nut($cookie = FALSE)   {
        $key = $cookie?'cookie':'url';
        return $this->nut[$key];
    }
    
    /**
     * Return encrypted nut defaults to URL or set $cookie to TRUE for cookie
     */
    public function get_encoded($cookie = FALSE)   {
        $key = $cookie?'cookie':'url';
        return $this->encoded[$key];
    }
    
    /**
     * Return uid after authentication default 0
     */
    public function get_uid()   {
        return $this->uid;
    }
    
    /**
     * Return clean property
     */
    public function is_clean()   {
        return $this->clean;
    }
    
    /**
     * Return status property
     */
    public function get_status()    {
        return $this->status;
    }
    
    /**
     * Return error property
     */
    public function is_error()    {
        return $this->error;
    }
    
    /**
     * Return error messages array
     */
    public function get_msgs()    {
        return $this->msg;
    }
    
    /**
     * Return timestamp of raw nut array
     */
    public function get_time()    {
        return $this->raw['url']['time'];
    }
    
    /**
     * Get nut from url and cookie parameters and validate
     */
    public function fetch()   {
        $this->clean = FALSE;
        $this->status = self::STATUS_FETCH;
        //set value for nut in url
        $this->nut['url']    = $_GET['nut'];
        $this->decrypt(self::SELECT_URL)->decode(self::SELECT_URL);
        //also set value of cookie thought may be empty
        $this->nut['cookie'] = isset($_COOKIE['sqrl'])?$_COOKIE['sqrl']:'';
        $this->decrypt(self::SELECT_COOKIE)->decode(self::SELECT_COOKIE);
        return $this;
    }
    
    /**
     * Build and return a complete nut object
     */
    public function build()    {
        try{
            $this->clean = FALSE;
            $this->build_nut();
            $this->encode(self::SELECT_URL);
            $this->encode(self::SELECT_COOKIE);
            $this->encrypt(self::SELECT_URL);
            $this->encrypt(self::SELECT_COOKIE);
            $this->set_cookie();
            //this assumes $params already set by prior call
            $this->cache_set();
            $this->status = self::STATUS_BUILD;
        } catch (Exception $e) {
            //TBD Exception handler
            $this->exception = $e;
            $this->error = TRUE;
        }
        return $this;
    }
}

/**
 * ****
 * **** Use examples
 * **** 
 */

/**
 * In all cases the currently active instance of the class can be fetched
 * by setting a variable equal to sqrl_instance::get_instance('sqrl_nut_drupal7'); thus call can
 * be made at the global scope without exposing a global variable.
 */

 //Build a new nut from system parameters
 /*
 $nut = sqrl_instance::get_instance('sqrl_nut_drupal7');
 if(!$nut->is_clean()) {//Optional error test for unclean instance}
 $nut->set_op_params($key, $value);//Needs to be done before build sets cache
 $nut->build();
 if($nut->is_error())   {//Test for any operational errors
    $msgs =$nut->get_msgs();//fetch any debugging messages
 }
 $nut_for_url = $nut->get_nut(FALSE);
 //Nut for cookie already stored in this version, may alias later
 */
 
 //Fetch nut from client request and vlaidate
 /*
 $nut = sqrl_instance::get_instance('sqrl_nut_drupal7');
 if(!$nut->is_clean()) {//Optional error test for unclean instance}
 $nut->fetch();
 $op_params = $nut->get_op_params();//Needs to be done after fetch
 if($nut->is_error())   {//Test for any operational errors
    $msgs =$nut->get_msgs();//fetch any debugging messages
 }
 */

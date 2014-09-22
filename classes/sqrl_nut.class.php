<?php
/**
 * @author ramriot
 */

/** build this for thowing and catching later
 * requires include of sqrl_exceptions.class.php
 */
class NutTestException extends CustomException {}
 
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
    function is_error();//Is there an operational error present
    function get_time();//Get timestamp from nut
    function get_op_params();//get an array of all stored operational parameters 
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
abstract class sqrl_nut extends sqrl_common implements sqrl_nut_api {
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
    const STATUS_BUILT      = 'built';
    const STATUS_FETCH      = 'fetched';
    const STATUS_DECODED    = 'decoded';
    const STATUS_NOCOOKIE   = 'nocookie';
    //internal variables
    protected $raw      =   array();//raw nut parameters assoc key url/cookie
    protected $encoded  =   array();//encoded nut strings assoc key url/cookie
    protected $nut      =   array();//encrypted nut strings assoc key url/cookie
    protected $params   =   array();//other parameters for storage in cache
    protected $error    =   FALSE;//Error flag
    protected $errorCode=   0;//Error code
    protected $msg      =   array();//Error messages
    protected $clean    =   TRUE;//Clean object flag
    protected $status   =   '';//Current object status string
    protected $op       =   '';//Current opperation (??)

    //Declair valid exception codes
    protected static $exceptions = array(
        'nutStLen'      =>  'Nut string length incorrect: ',
        'nutStChk'      =>  'Nut status check failed: @thisStatus != @chkStatus',
        'nutFeUrl'      =>  'Nut missing from GET request',
        'nutFeCookie'   =>  'Nut missing from COOKIE request',
        'nutRawMatch'   =>  'Nut in url and cookie raw parameter arrays do not match',
        'nutEncMatch'   =>  'Nut in url and cookie encoded strings do not match',
        'nutExpired'    =>  'Nut time validity expired',
        'nutDirty'      =>  'Clean nut expected dirty nut found',
        'nutClean'      =>  'Dirty nut expected clean nut found',
    );
    
    private function throw_new_nut_exception($errorCode, $tokens = array()) {
        $errorMsg = $this->format_string($this->exceptions[$errorCode], $tokens);
        throw new NutTestException($errorMsg , $errorCode);    
    }
    

    /**
     * **** All these abstract methods must be present in child classes
     */
    //take $this->encoded => $this->nut
    abstract protected function encrypt();
    //take $this->nut => $this->encoded
    abstract protected function decrypt();
    //return the base url of the site
    abstract protected function get_base_url();
    //set a persistent cache
    abstract protected function cache_set();
    //get a named cache item
    abstract protected function cache_get();
    /**
     * abstract method to implement named 32 bit wrapping
     * counters this function is to be overriden by higher
     * level implementor class.
     * @param $name String: machine readable string =<255
     *  characters long ot act as counter key
     * @return Int: new count value
     */
    abstract protected function get_named_counter($name);

    /**
     * Build and return a complete nut object
     */
    public function build_nuts($params = array())    {
        //this operation to be done against a clean object only
        try{
            $this->is_clean();
            $this->clean = FALSE;
            $this->set_op_params($params);
            $this->source_raw_nuts();
            $this->encode_raw_nuts();
            $this->encrypt();
            $this->cache_set();
            $this->set_cookie();
            $this->status = self::STATUS_BUILT;
        }
        catch(NutTestException $e)//exceptions we throw
        {
            //TBD use the generated code as passback and trigger
            $this->errorCode = $e->getCode();
            $this->msg = $e->getMessage();
        }
        //Don't catch standart exceptions
        /*
        catch(Exception $e)//exceptions php throws
        {
            echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
        }
        */
        //If PHP >=5.3 a finally clause can be added here to catch rest for now we let it drop through
        return $this;
    }

    /**
     * Get nut from url and cookie parameters
     */
    public function fetch_nuts($cookie_expected = FALSE)   {
        //this operation to be done against a clean object only
        try{
            $this->is_clean();
            $this->clean = FALSE;
            $this->status = self::STATUS_FETCH;
            //set value for nut in url
            $this->nut['url']    = fetch_url_nut();
            if($cookie_expected)
            {
                $this->nut['cookie'] = fetch_cookie_nut();
            }
            $this->decrypt($cookie_expected);
            $this->decode_encoded_nuts($cookie_expected);
            $this->cache_get();
            $this->status = self::STATUS_DECODED;
        }
        catch(NutTestException $e)//exceptions we throw
        {
            //TBD use the generated code as passback and trigger
            $this->errorCode = $e->getCode();
            $this->msg = $e->getMessage();
        }
        /*
        catch(Exception $e)//exceptions php throws
        {
            echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
        }
        */
        //If PHP >=5.3 a finally clause can be added here to catch rest for now we let it drop through
        return $this;
    }
    
    public function is_valid_nuts($cookie_expected) {
        try{
            $this->is_dirty();
            //check status
            $this->is_nut_status(self::STATUS_DECODED);
            //check for expired nut in url
            $this->is_raw_nut_expired(self::SELECT_URL);
            //if cookie expected
            if($cookie_expected)
            {
                $this->is_raw_nut_expired(self::SELECT_COOKIE);
                $this->is_match_encoded_nuts();
            }
        }
        catch(NutTestException $e)//exceptions we throw
        {
            //TBD use the generated code as passback and trigger
            $this->errorCode = $e->getCode();
            $this->msg = $e->getMessage();
        }
        /*
        catch(Exception $e)//exceptions php throws
        {
            echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
        }
        */
        //If PHP >=5.3 a finally clause can be added here to catch rest for now we let it drop through
        return $this;
    }
    
    public function source_raw_nuts()    {
        $this->raw['url'] = array(
            'time'      =>  $_SERVER['REQUEST_TIME'],
            'ip'        =>  $this->_get_ip_address(),
            'counter'   =>  $this->get_named_counter('sqrl_nut'),
            'random'    =>  $this->_get_random_bytes(4),
        );
        //clone raw url data to raw cookie
        $this->raw['cookie']    =   $this->raw['url'];
        return $this;
    }
    
    /**
     * encode raw nut array into a byte string
     */
    public function encode_raw_nuts()    {
        $keys = array('cookie','url');
        foreach($keys as $key)  {
            $ref = & $this->raw[$key];
            //format bytes
            $output = pack('LLL', $ref['time'], $this->_ip_to_long($ref['ip']), $ref['counter']) . $ref['random'];
            $this->encoded[$key] = $output;
        }
        return $this;
    }

    /**
     * decode encoded byte string into array parts
     */
    public function decode_encoded_nuts($cookie_expected) {
        $keys = array('url');
        if($cookie_expected) $keys[] = 'cookie';
        foreach($keys as $key)  {
            $ref = & $this->encoded;
            //test byte string
            if(strlen($ref[$key]) == 16)
            {
                $this->raw[$key] = array(
                  'time'    => $this->decode_time($ref[$key]),
                  'ip'      => $this->decode_ip($ref[$key]),
                  'counter' => $this->decode_counter($ref[$key]),
                  'random'   => $this->decode_random($ref[$key]),
                );
            }
            else
            {
                throw_new_nut_exception('nutStLen', array('@len'=>strlen($ref[$key])));
            }
        }
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
    
    public function set_cookie()   {
        setcookie('sqrl', $this->nut['cookie'], $_SERVER['REQUEST_TIME'] + self::NUT_LIFETIME, '/', $this->get_base_url());
        return $this;
    }
    
    /*
     * 
     */
    private function is_nut_status($status) {
        if($this->status !== $status)   {
            throw_new_nut_exception('nutStChk', array('@thisStatus' => $this->status, '@chkStatus' => $status));
        }
    }
    
    /*
     * fetch nut from url and test return
     */
    public function fetch_url_nut()  {
        $nut = isset($_GET['nut'])?$_GET['nut']:FALSE;
        if(!$nut) throw_new_nut_exception('nutFeUrl');
        return $nut;
    }
    
    /*
     * fetch nut from cookie and test return
     */
    public function fetch_cookie_nut()  {
        $nut = isset($_COOKIE['sqrl'])?$_COOKIE['sqrl']:'';
        if(!$nut) throw_new_nut_exception('nutFeCookie');
        return $nut;
    }
    
    /*
     * function to time safe compare raw nuts
     */
    public function is_match_raw_nuts()     {
        $error = FALSE;
        foreach ($this->raw['url'] as $key => $value) {
            if ($this->raw['cookie'][$key] != $value) {
                $error = TRUE;
                break;
            }
        }
        if($error)
        {
            throw_new_nut_exception('nutRawMatch');
        }
        return $this;
    }
    
    /*
     * function to time safe compare encoded nuts
     */
    public function is_match_encoded_nuts()     {
        $str_url    = $this->encoded['url'];
        $str_cookie = $this->encoded['cookie'];
        if(!$this->time_safe_strcomp($str_url, $str_cookie))
        {
            throw_new_nut_exception('nutEncMatch');
        }
        return $this;
    }
    
    /**
     * function to check if decoded nut is expired
     */
    public function is_raw_nut_expired($cookie)    {
        $type = $cookie?'cookie':'url';
        $nut = get_raw_nut($cookie);
        if ($nut['time'] < $_SERVER['REQUEST_TIME'])
        {
            throw_new_nut_exception('nutExpired');
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
    private function set_op_param($key = '', $value = '') {
      if (!empty($key)) {
        $this->params[$key] = $value;
      }
      return $this;
    }

    private function set_op_params($params = array()) {
        foreach($params as $key => $value)    {
            set_op_param($key, $value);
        }
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
    public function get_op_param($key = null) {
        if(null === $key)    {
            return $this->params;
        }
        return $this->params[$key];
    }

    /**
     * Return encrypted nut defaults to URL or set $cookie to TRUE for cookie
     */
    public function get_encrypted_nut($cookie = FALSE)   {
        $key = $cookie?'cookie':'url';
        return $this->nut[$key];
    }
    
    /**
     * Return encrypted nut defaults to URL or set $cookie to TRUE for cookie
     */
    public function get_encoded_nut($cookie = FALSE)   {
        $key = $cookie?'cookie':'url';
        return $this->encoded[$key];
    }
    
    /**
     * Return encrypted nut defaults to URL or set $cookie to TRUE for cookie
     */
    public function get_raw_nut($cookie = FALSE)   {
        $key = $cookie?'cookie':'url';
        return $this->raw[$key];
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
        if(!$this->clean)    throw_new_nut_exception('nutDirty');
        return $this;
    }
    
    /**
     * Return clean property
     */
    public function is_dirty()   {
        if($this->clean)    throw_new_nut_exception('nutClean');
        return $this;
    }
    
    /**
     * Return status property
     */
    public function get_status()    {
        return $this->status;
    }
        
    /**
     * Return error messages array
     */
    public function get_msgs()    {
        return $this->msg;
    }
    
    /**
     * Return error messages array
     */
    public function get_errorCode()    {
        return $this->errorCode;
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
 $nut = sqrl_instance::get_instance('sqrl_nut');
 $nut->build($params);
 $nut_for_url = $nut->get_nut(FALSE);
 //Nut for cookie already stored in this version, may alias later
 */
 
 //Fetch nut from client request and vlaidate
 /*
 $nut = sqrl_instance::get_instance('sqrl_nut_drupal7');
 $nut->fetch();
 if($nut->validate_nuts($cookie_expected))   {
    $msgs =$nut->get_msgs();//fetch any debugging messages
 }
 */

<?php
/**
 * @author ramriot
 */

/** build this for thowing and catching later
 * requires include of sqrl_exceptions.class.php
 */
class NutTestException extends CustomException {}
 
/**
 * Interface to sqrl_nut class
 */
interface sqrl_nut_api {
    //compound methods
    function build_nuts();//Build a new nut object
    function fetch_nuts();//Fetch nut from client request and validate
    //test methods
    function is_valid_nuts($cookie_expected = FALSE);
    //Get methods
    function get_encrypted_nut($key);
    function get_encoded_nut($key);
    function get_raw_nut($key);
    function get_status();//Get operation status
    function get_msg();//Get any debugging message
    function is_exception();//Is there an operational exception present
    function get_op_param($key = null);
}

/**
 * ****
 * **** This class defines the main structure for SQRL nut building,
 * **** encoding, decoding and validation.
 * ****
 */
abstract class sqrl_nut extends sqrl_common implements sqrl_nut_api {
    const NUT_LIFETIME      = 600;
    //selector constants
    const SELECT_COOKIE     = 'cookie';
    const SELECT_URL        = 'url';
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

    //Declare valid exception codes
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
        'notNutKey'        =>  'Cookie / Nut selector not passing correct key'
    );
    
    private function nut_key($key)  {
        if($key !== self::SELECT_COOKIE || $key !== self::SELECT_URL)
        {
            throw_new_nut_exception('notNutKey');
        }
        return $this;
    }
    
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
     * @param $cookie_expected Boolean
     */
    public function fetch_nuts($cookie_expected = FALSE)   {
        //this operation to be done against a clean object only
        try{
            $this->is_clean();
            $this->clean = FALSE;
            $this->status = self::STATUS_FETCH;
            //set value for nut in url
            $this->nut[self::SELECT_URL]    = fetch_url_nut();
            if($cookie_expected)
            {
                $this->nut[self::SELECT_COOKIE] = fetch_cookie_nut();
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

    /**
     * Return encrypted nut
     * @param $key constant for selection
     * @return URL safe String
     */
    public function get_encrypted_nut($key)   {
        $this->nut_key($key);
        return $this->nut[$key];
    }
    
    /**
     * Return encoded nut / normally both url and cookie the same
     * @param $key constant for selection
     * @return Byte String
     */
    public function get_encoded_nut($key)   {
        $this->nut_key($key);
        return $this->encoded[$key];
    }
    
    /**
     * Return raw nut array
     * @param $key constant for selection
     * @return Array
     */
    public function get_raw_nut($key)   {
        $this->nut_key($key);
        return $this->raw[$key];
    }
    
    /**
     * Return status property
     */
    public function get_status()    {
        return $this->status;
    }
        
    /**
     * Return error messages string
     */
    public function get_msg()    {
        return $this->msg;
    }
    
    /**
     * Return error messages code
     */
    public function get_errorCode()    {
        return $this->errorCode;
    }
    
    public function is_exception()  {
        return $this->errorCode?TRUE:FALSE;
    }
    
    /**
     * Getter for additional parameters that will get integrated into
     * the NUT. This is useful so that the request from the SQRL client
     * will contain those extra parameters as the server may need them
     * to perform the required operation associated with the SQRL request.
     * @param $key String Key used for saved parameter or leave empty for
     * all parameters as array
     * @return String for a provided key and an array for empty
     */
    public function get_op_param($key = null) {
        if(null === $key)    {
            return $this->params;
        }
        return $this->params[$key];
    }
    
    private function source_raw_nuts()    {
        $this->raw[self::SELECT_URL] = array(
            'time'      =>  $_SERVER['REQUEST_TIME'],
            'ip'        =>  $this->_get_ip_address(),
            'counter'   =>  $this->get_named_counter('sqrl_nut'),
            'random'    =>  $this->_get_random_bytes(4),
        );
        //clone raw url data to raw cookie
        $this->raw[self::SELECT_COOKIE]    =   $this->raw[self::SELECT_URL];
        return $this;
    }
    
    /**
     * encode raw nut array into a byte string
     */
    private function encode_raw_nuts()    {
        $keys = array( self::SELECT_COOKIE, self::SELECT_URL );
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
    private function decode_encoded_nuts($cookie_expected) {
        $keys = array(self::SELECT_URL);
        if($cookie_expected) $keys[] = self::SELECT_COOKIE;
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
    
    private function set_cookie()   {
        setcookie('sqrl', $this->nut[self::SELECT_COOKIE], $_SERVER['REQUEST_TIME'] + self::NUT_LIFETIME, '/', $this->get_base_url());
        return $this;
    }
    
    private function is_nut_status($status) {
        if($this->status !== $status)   {
            throw_new_nut_exception('nutStChk', array('@thisStatus' => $this->status, '@chkStatus' => $status));
        }
    }
    
    private function is_clean()   {
        if(!$this->clean)    throw_new_nut_exception('nutDirty');
        return $this;
    }
    
    private function is_dirty()   {
        if($this->clean)    throw_new_nut_exception('nutClean');
        return $this;
    }

    private function fetch_url_nut()  {
        $nut = isset($_GET['nut'])?$_GET['nut']:FALSE;
        if(!$nut) throw_new_nut_exception('nutFeUrl');
        return $nut;
    }
    
    private function fetch_cookie_nut()  {
        $nut = isset($_COOKIE['sqrl'])?$_COOKIE['sqrl']:'';
        if(!$nut) throw_new_nut_exception('nutFeCookie');
        return $nut;
    }
    
    private function is_match_raw_nuts()     {
        $error = FALSE;
        foreach ($this->raw[self::SELECT_URL] as $key => $value) {
            if ($this->raw[self::SELECT_COOKIE][$key] != $value) {
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
    
    private function is_match_encoded_nuts()     {
        $str_url    = $this->encoded[self::SELECT_URL];
        $str_cookie = $this->encoded[self::SELECT_COOKIE];
        if(!$this->time_safe_strcomp($str_url, $str_cookie))
        {
            throw_new_nut_exception('nutEncMatch');
        }
        return $this;
    }
    
    private function is_raw_nut_expired($key)    {
        $this->nut_key($key);
        $nut = get_raw_nut($key);
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

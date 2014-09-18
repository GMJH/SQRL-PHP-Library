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

    //decodes nut to original data.
    public function decode_encoded_nuts() {
        $keys = array('cookie','url');
        foreach($keys as $key)  {
            $ref = & $this->encoded;
            if(!empty($ref[$key]))
            {
                $this->raw[$key] = array(
                  'time'    => $this->decode_time($ref[$key]),
                  'ip'      => $this->decode_ip($ref[$key]),
                  'counter' => $this->decode_counter($ref[$key]),
                  'random'   => $this->decode_random($ref[$key]),
                );
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
     * function to time safe compare raw nuts
     */
    public function is_match_raw_nuts()     {
        $error = FALSE;
        foreach ($this->raw['url'] as $key => $value) {
            if ($this->raw['cookie'][$key] != $value) {
                $error = TRUE;
                // Nuts don't match, so we reject this request too.
                //$this->error = TRUE;
                //$this->msg[] = array(self::VALIDATION_FAILED, '');//TBD add meaningful words here
                break;
            }
        }
        /*
        if(!$this->error)   {
            $this->status = self::STATUS_VALID;
            $this->cache_get();
            if (empty($this->params)) {
                $this->params['op'] = 'login';
            }
        }
        return $this;
        */
        return $error;
    }
    
    /*
     * function to time safe compare encoded nuts
     */
    public function is_match_encoded_nuts()     {
        $str_url    = $this->encoded['url'];
        $str_cookie = $this->encoded['cookie'];
        $valid = $this->time_safe_strcomp($str_url, $str_cookie);
        return $valid;
    /*
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
    */
    }
    
    /**
     * function to check if decoded nut is expired
     */
    public function is_raw_nut_expired($cookie)    {
        $nut = get_raw_nut($cookie);
        return ($nut['time'] >= $_SERVER['REQUEST_TIME']);
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
     * Get nut from url and cookie parameters
     */
    public function fetch_nuts()   {
        if($this->clean)   {
            $this->clean = FALSE;
            $this->status = self::STATUS_FETCH;
            //set value for nut in url
            $this->nut['url']    = isset($_GET['nut'])?$_GET['nut']:'';
            $this->nut['cookie'] = isset($_COOKIE['sqrl'])?$_COOKIE['sqrl']:'';
            $this->decrypt();
            $this->decode();
        } else {
            trigger_error('fetch_nuts called on unclean instance sqrl status: '.$this->status);
        }
        return $this;
    }
    
    /**
     * Build and return a complete nut object
     */
    public function build_nuts()    {
        if($this->clean)   {
            $this->clean = FALSE;
            $this->source_raw_nuts();
            $this->encode_raw_nuts();
            $this->encrypt();
            $this->set_cookie();
            //this assumes $params already set by prior call
            $this->cache_set();
            $this->status = self::STATUS_BUILD;
        } else {
            trigger_error('build_nuts called on unclean instance sqrl status: '.$this->status);
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

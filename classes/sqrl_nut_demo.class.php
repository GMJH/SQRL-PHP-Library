<?php
/**
 * @author ramriot
 */

/**
 * ****
 * **** This class defines the specific overridden methods for my_demo
 * **** compatability to the underlying class. NB:Other implementors can
 * **** override this class in the same way to integrate into their own
 * **** frameworks.
 * **** 
 */
class sqrl_nut_my_demo extends sqrl_nut {
    //constants can be overriden
    const NUT_LIFETIME      = 300;    
    /**
     * **** methods required from parent
     */
    
    //take $this->encoded => $this->nut
    /* Required dependancy on drupal aes module configured by sqrl module*/ 
    protected function encrypt() {
        $keys = array(0=>parent::SELECT_URL,1=>parent::SELECT_COOKIE);
        foreach($keys as $cookie=>$keys) {
            $ref = & $this->encoded[$key];
            $this->nut[$key] = $this->sqrl_aes_encrypt($ref, $cookie);
        }
        return $this;
    }
    
    //take $this->nut => $this->encoded
    /* Required dependancy on drupal aes module configured by sqrl module*/ 
    public function decrypt($cookie) {
        $keys = array(0=>parent::SELECT_URL,1=>parent::SELECT_COOKIE);
        foreach($keys as $cookie=>$keys) {
            $ref = & $this->nut[$key];
            $this->encoded[$key] = $this->sqrl_aes_decrypt($ref, $cookie);
        }
        return $this;
    }
    
    function sqrl_aes_encrypt($data, $cookie = TRUE) {
        $data = aes_encrypt($data, TRUE, _sqrl_aes_get_key($cookie), _sqrl_aes_get_cipher($cookie), _sqrl_aes_get_iv($cookie), _sqrl_aes_get_implementation($cookie));
        return strtr($data, array('+' => '-', '/' => '_', '=' => ''));
    }

    function sqrl_aes_decrypt($data, $cookie = TRUE) {
        $data = strtr($data, array('-' => '+', '_' => '/')) . '==';
        return aes_decrypt($data, TRUE, _sqrl_aes_get_key($cookie), _sqrl_aes_get_cipher($cookie), _sqrl_aes_get_iv($cookie), _sqrl_aes_get_implementation($cookie));
    }

    
    //return the base url of the site
    /* Use local sqrl module function TBD: define universal function */
    protected function get_base_url()   {
        return sqrl_get_base_url();
    }
    
    //set a persistent cache
    /* Use D7 cache process for storage between requests */
    protected function cache_set()    {
        $cid = 'SQRL:NUT:PARAMS:' . $this->nut[parent::SELECT_URL];
        cache_set($cid, $this->params, 'cache', $_SERVER['REQUEST_TIME'] + self::NUT_LIFETIME);
        return $this;
    }
    
    //get a named cache item
    /* Use D7 cache process for storage between requests */
    protected function cache_get()    {
        $cid = 'SQRL:NUT:PARAMS:' . $this->nut[parent::SELECT_URL];
        $this->params = cache_get($cid, 'cache');
        return $this;
    }
    
    /* Use D7 varaible table as storage for counter */
    protected function get_named_counter($name) {
        $counter = variable_get('sqrl_'.$name, 0);
        //Reset on 32 bit rollover
        if($counter > (2^32-1)) {
            $counter = 0;
            variable_set('sqrl_'.$name, $counter);
        } else {
            variable_set('sqrl_'.$name, $counter + 1);
        }
        return $counter;
    }

}

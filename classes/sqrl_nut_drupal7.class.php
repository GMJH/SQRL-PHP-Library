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
class sqrl_nut_drupal7 extends sqrl_nut {
    //constants can be overriden
    const NUT_LIFETIME      = 300;
    
    protected $crypt        = array();
    /**
     * **** methods required from parent
     */
    
    private function encrypt($data, $part) {
        return aes_encrypt(
                            $data,
                            TRUE,
                            $this->sqrl_aes_get($part, 'key'),
                            $this->sqrl_aes_get($part, 'cipher'),
                            $this->sqrl_aes_get($part, 'iv'),
                            $this->sqrl_aes_get($part, 'implementation')
                            );
    }

    private function decrypt($data, $part) {
        return aes_decrypt(
                           $data,
                           TRUE,
                           $this->sqrl_aes_get($part, 'key'),
                           $this->sqrl_aes_get($part, 'cipher'),
                           $this->sqrl_aes_get($part, 'iv'),
                           $this->sqrl_aes_get($part, 'implementation')
                           );
    }
    
    private function sqrl_aes_get($key, $part) {
        $this->nut_key($key);
        return $this->crypt[$key][$part];
    }

    private function sqrl_aes_set($key, $crypt) {
        $this->nut_key($key);
        $this->crypt[$key] = $crypt;
        return $this;
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

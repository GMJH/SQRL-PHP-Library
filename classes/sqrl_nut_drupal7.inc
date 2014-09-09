<?php
/**
 * @author ramriot
 */

/**
 * ****
 * **** This class defines the specific overridden methods for drupal 7
 * **** compatability to the underlying class. NB:Other implementors can
 * **** override this class in the same way to integrate into their own
 * **** frameworks.
 * **** 
 */
class sqrl_nut_drupal7 extends sqrl_nut {
    
    /**
     * Overriden Primitives for drupal 7 and sqrl module use
     */
    
    /* Preferred D7 defined constant for request timestamp*/
    protected function get_request_time()   {
        return REQUEST_TIME;
    }
    
    /* Available function in D7 for client IP address*/
    protected function get_ip_address() {
        return ip_address();
    }
    
    /* Use D7 varaible table as storage for counter */
    protected function get_counter()   {
        $counter = variable_get('sqrl_increment', 0) + 1;
        variable_set('sqrl_increment', $counter);
        return $counter;
    }
    
    /* Available D7 good random bytes function */
    protected function get_random_bytes($count)   {
        return drupal_random_bytes($count);
    }
    
    /* Required dependancy on drupal aes module configured by sqrl module*/    
    public function encrypt($cookie) {
        $key = $cookie?'cookie':'url';
        $ref = & $this->encoded[$key];
        $this->nut[$key] = sqrl_aes_encrypt($ref, $cookie);
        return $this;
    }
    
    /* Required dependancy on drupal aes module configured by sqrl module*/    
    public function decrypt($cookie) {
        $key = $cookie?'cookie':'url';
        $ref = & $this->nut[$key];
        $this->encoded[$key] = sqrl_aes_decrypt($ref, $cookie);
        return $this;
    }

    /* Use D7 cache process for storage between requests */
    protected function cache_set()    {
        $cid = 'SQRL:NUT:PARAMS:' . $this->nut['url'];
        cache_set($cid, $this->params, 'cache', $this->get_request_time() + self::NUT_LIFETIME);
        return $this;
    }
    
    /* Use D7 cache process for storage between requests */
    protected function cache_get()    {
        $cid = 'SQRL:NUT:PARAMS:' . $this->nut['url'];
        $this->params = cache_get($cid, 'cache');
        return $this;
    }
    
    /* Use local sqrl module function TBD: define universal function */
    protected function get_base_url()   {
        return sqrl_get_base_url();
    }
}

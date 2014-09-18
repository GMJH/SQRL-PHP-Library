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
    //constants can be overriden
    const NUT_LIFETIME      = 300;    
    /**
     * **** methods required from parent
     */
    
    //take $this->encoded => $this->nut
    /* Required dependancy on drupal aes module configured by sqrl module*/ 
    protected function encrypt() {
        $keys = array(0=>'url',1=>'cookie');
        foreach($keys as $cookie=>$keys) {
            $ref = & $this->encoded[$key];
            $this->nut[$key] = sqrl_aes_encrypt($ref, $cookie);
        }
        return $this;
    }
    
    //take $this->nut => $this->encoded
    /* Required dependancy on drupal aes module configured by sqrl module*/ 
    public function decrypt($cookie) {
        $keys = array(0=>'url',1=>'cookie');
        foreach($keys as $cookie=>$keys) {
            $ref = & $this->nut[$key];
            $this->encoded[$key] = sqrl_aes_decrypt($ref, $cookie);
        }
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
        $cid = 'SQRL:NUT:PARAMS:' . $this->nut['url'];
        cache_set($cid, $this->params, 'cache', $_SERVER['REQUEST_TIME'] + self::NUT_LIFETIME);
        return $this;
    }
    
    //get a named cache item
    /* Use D7 cache process for storage between requests */
    protected function cache_get()    {
        $cid = 'SQRL:NUT:PARAMS:' . $this->nut['url'];
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

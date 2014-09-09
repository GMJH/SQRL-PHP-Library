<?php
/**
 * @author ramriot
 */

/**
 * An override class for Drupal 7 SQRL client operations
 *
 * @author ramriot
 *
 * @link   
 */
class sqrl_client_drupal7 extends sqrl_client
{
    /*
    public function __construct()
    {
        parent::__construct();
        return $this;
    }
    */
    
    /**
     * https://www.grc.com/sqrl/semantics.htm: The data to be signed are the two
     * base64url encoded values of the “client=” and “server=” parameters with the
     * “server=” value concatenated to the end of the “client=” value.
     */
    public function process()   {
        
        //current extent of signatures
        $signatures = array(
            'ids'   => _sqrl_client_base64_decode($this->_POST['ids']),
            'pids'  => _sqrl_client_base64_decode($this->_POST['pids']),
            'urs'   => _sqrl_client_base64_decode($this->_POST['urs']),
        );
        //default vars array to be populated by processors
        $this->vars = array(
            'http_code' => 403,
            'tif' => 0,
            'header' => array(
                'host'  => $this->_get_server_value('HTTP_HOST'),
                'auth'  => $this->_get_server_value('HTTP_AUTHENTICATION'),
                'agent' => $this->_get_server_value('HTTP_USER_AGENT'),
            ),
            'client' => $this->_decode_parameter($this->_POST['client']),
            'server' => $this->_decode_parameter($this->_POST['server']),
            'validation_string' => $this->_POST['client'] . $this->_POST['server'],
            'signatures' => $signatures,
            'nut' => $_GET['nut'],
            'account' => FALSE,
            'valid' => FALSE,
            'response' => array(),
            'fields' => array(),
        );
        return $this;
    }
    
    public function get_vars()  {
        return $this->vars;
    }
    
    /**
     * Are required signatures Present.
     * @param $sigKeys Array of signature keys that are required or string for single
     * @return Boolean False if required key/s are missing other wise return TRUE
     * @sideffect Sets error responses for later use
     */
    public function required_keys($sigKeys)
    {
        $result = TRUE;
        if(is_array($sigKeys))
        {
            foreach($sigKeys as $key)
            {
                if(!$this->required_key($sigKey)) $result = FALSE;
            }
        }
        else
        {
            $result = $this->required_key($sigKey);
        }
        return $result;
    }

    private function required_key($Key, $type = 'sig')    {
        if(is_string($Key) && strlen($Key))
        {
            $response = TRUE;
            switch($type)
            {
                case 'sig':
                    if(empty($this->vars['signature'][$Key]))
                    {
                        $this->set_message($this->t('Required sig @key Missing', array('@key'=>$key)), SQRL_MSG_ERROR);
                        $response = FALSE;
                    }
                    break;
                case 'pub':
                    if(empty($this->vars['client'][$Key]))
                    {
                        $this->set_message($this->t('Required pk @key Missing', array('@key'=>$key)), SQRL_MSG_ERROR);
                        $response = FALSE;
                    }
                    break;
            }
        }
        else
        {
            $response = FALSE;
            $this->set_message($this->t('Bad Call to required_keys'), SQRL_MSG_ERROR);
        }
        return $response;
    }
    
    /**
     * list all signatures present
     */
    public function signatures()    {
        $sigKeys = array();
        foreach($this->vars['signatures'] as $key=>$sig)    {
            if(!empty($sig)) $sigKeys[] = $key;
        }
        return $sigKeys;
    }
    
    /**
     * Are required signatures Present.
     * @param $sigRef Array keyed by signature key with values pk key for validation
     * @return Boolean False if any validation fales or true otherwise
     * @sideffect Sets error responses for later retreval
     */
    // Signatures Validate against relevent pk and msg.
    public function validate_signature($sigKey, $pkKey)
    {
        //get signature
        if(!required_key($sigKey, 'sig'))   return FALSE;
        $sig = $this->vars['signatures'][$sigKey];
        //get related pk
        if(!required_key($pkKey, 'pub'))   return FALSE;
        $pk  = $this->vars['client'][$pkKey];
        //validate
        return ed25519_checkvalid($sig, $msg, $pk);
    }
    
    // TODO: Check the header values.
    public function check_header_values()
    {
        
    }
    // TODO: Check the client values.
    public function check_client_values()
    {
        
    }
    // TODO: Check the server values.
    public function check_server_values()
    {
        
    }
    // Validate nut
    public function validate_nut()
    {
        
    }    
    // Validate same IP policy
    public function validate_same_ip()
    {
        
    }
    
    
    public function checkvalid($s, $m, $pk)
    {
        if (strlen($s) != $this->b/4) {
            throw new \Exception('Signature length is wrong');
        }
        if (strlen($pk) != $this->b/8) {
            throw new \Exception('Public key length is wrong: '.strlen($pk));
        }
        $R = $this->decodepoint(substr($s, 0, $this->b/8));
        try {
            $A = $this->decodepoint($pk);
        } catch (\Exception $e) {
            return false;
        }
        $S = $this->decodeint(substr($s, $this->b/8, $this->b/4));
        $h = $this->Hint($this->encodepoint($R).$pk.$m);

        return $this->scalarmult($this->B, $S) == $this->edwards($R, $this->scalarmult($A, $h));
    }
}
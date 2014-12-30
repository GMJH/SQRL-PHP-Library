<?php

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
$nut = sqrl_instance::get_instance('sqrl_nut');
$nut->build($params);
$nut_for_url = $nut->get_nut(FALSE);
//Nut for cookie already stored in this version, may alias later

//Fetch nut from client request and vlaidate
$nut = sqrl_instance::get_instance('sqrl_nut_drupal7');
$nut->fetch();
if ($nut->validate_nuts($cookie_expected)) {
  $msgs = $nut->get_msgs();//fetch any debugging messages
}

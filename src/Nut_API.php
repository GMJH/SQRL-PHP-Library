<?php
/**
 * SQRL
 *
 * Copyright (c)
 *
 * Description
 *
 * Licence
 *
 * @package    SQRL
 * @author     Jürgen Haas <juergen@paragon-es.de>
 * @author     Gary Marriott <ramriot@gmail.com>
 * @copyright  ...
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       https://bitbucket.org/jurgenhaas/sqrl-php
 */

namespace JurgenhaasRamriot\SQRL;

/**
 * Interface to Nut class
 */
interface Nut_API {
  //compound methods
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

}

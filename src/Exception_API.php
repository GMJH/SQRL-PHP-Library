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

interface Exception_API {
  /* Protected methods inherited from Exception class */
  public function getMessage();                 // Exception message

  public function getCode();                    // User-defined Exception code

  public function getFile();                    // Source filename

  public function getLine();                    // Source line

  public function getTrace();                   // An array of the backtrace()

  public function getTraceAsString();           // Formated string of trace

  /* Overrideable methods inherited from Exception class */
  public function __toString();                 // formated string for display

  public function __construct($message = NULL, $code = 0);
}

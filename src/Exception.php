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

abstract class Exception extends \Exception {

  protected $message = 'Unknown exception'; // Exception message
  protected $code = 0;                      // User-defined exception code
  protected $file;                          // Source filename of exception
  protected $line;                          // Source line of exception

  public function __construct($message = NULL, $code = 0) {
    if (!$message) {
      throw new $this('Unknown ' . get_class($this));
    }
    parent::__construct($message, $code);
  }

  public function __toString() {
    return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n"
    . "{$this->getTraceAsString()}";
  }

}

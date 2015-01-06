<?php
/**
 * SQRL
 *
 * Copyright (c) 2014-2015 Gary Marriott & Jürgen Haas
 *
 * Description
 *
 * Licence     GNU LGPL V3
 *
 * @package    SQRL
 * @author     Jürgen Haas <juergen@paragon-es.de>
 * @author     Gary Marriott <ramriot@gmail.com>
 * @copyright  2014-2015 Gary Marriott & Jürgen Haas
 * @license    http://opensource.org/licenses/LGPL-3.0
 * @link       https://github.com/GMJH/SQRL-PHP-Library
 */

namespace GMJH\SQRL;

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

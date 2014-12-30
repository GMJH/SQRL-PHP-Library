<?php
/**
 * ****
 * **** include this one file to include all the classes in the /classes
 * **** folder.
 * ****
 */

/*
 * Version: 0.0.1
 * Build: 20141002-01
 */

define('SQRLROOT', dirname(__FILE__));
define('SQRLPATHSEPARATOR', DIRECTORY_SEPARATOR);
//Register the following function to load all classes
spl_autoload_register('sqrlautoload');

/**
 * autoload
 *
 * @author Joe Sexton <joe.sexton@bigideas.com>
 * @param  string $class
 * @param  string $dir
 * @return bool
 */
function sqrlautoload($class, $dir = NULL) {
  if (is_null($dir)) {
    $dir = SQRLROOT . SQRLPATHSEPARATOR . 'classes';
  }
  foreach (scandir($dir) as $file) {
    // directory?
    if (is_dir($dir . $file) && substr($file, 0, 1) !== '.') {
      sqrlautoload($class, $dir . $file . SQRLPATHSEPARATOR);
    }
    // php file?
    if (substr($file, 0, 2) !== '._' && preg_match("/.php$/i", $file)) {
      // filename matches class?
      if (str_replace('.php', '', $file) == $class || str_replace('.class.php', '', $file) == $class) {
        include $dir . $file;
      }
    }
  }
}

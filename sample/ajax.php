<?php

if (empty($_GET['operation'])) {
  exit;
}
include_once 'init.php';

switch ($_GET['operation']) {
  case 'markup':
    $sqrl = new \GMJH\SQRL\Sample\SQRL(FALSE);
    print $sqrl->get_markup($_GET['operation'], TRUE, TRUE);
    break;

  case 'poll':
    $sqrl = new \GMJH\SQRL\Sample\SQRL(TRUE, TRUE);
    $sqrl->poll();
    break;

}

<?php

include_once 'init.php';

$sqrl = new \GMJH\SQRL\Sample\SQRL(TRUE);
new \GMJH\SQRL\Sample\Client($sqrl);

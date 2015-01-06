<?php

include_once 'init.php';

$sqrl = new \GMJH\SQRL\Sample\SQRL(TRUE, TRUE);
$sqrl->get_qr_image();

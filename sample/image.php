<?php

include_once 'init.php';

$sqrl = new \JurgenhaasRamriot\SQRL\Sample\SQRL(TRUE, TRUE);
$sqrl->get_qr_image();

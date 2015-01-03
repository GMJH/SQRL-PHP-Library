<?php

include_once 'init.php';

$sqrl = new \JurgenhaasRamriot\SQRL\Sample\SQRL(TRUE, TRUE);

if (!$sqrl->is_valid()) {
  header('Status: 404 Not Found');
  print '';
  exit;
}

$string = $sqrl->get_nut_url();
header('Content-type: image/png');
echo QRcode::png($string, FALSE, QR_ECLEVEL_L, 3, 4, FALSE);

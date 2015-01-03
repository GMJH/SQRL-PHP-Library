<?php

include_once 'init.php';

$sqrl = new \JurgenhaasRamriot\SQRL\Sample\SQRL(FALSE);

$url = $sqrl->get_nut_url();
$path = $sqrl->get_path('image.php');

$image = '<img src="' . $path . '" width="160" height="160" alt="SQRL" title="SQRL">';
$output = '<a href="' . $url . '">' . $image . '</a>';

print '<div id="sqrl-login" class="sqrl login">' . $output . '</div>';

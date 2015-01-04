<!DOCTYPE html>
<html>
<head lang="en">
  <meta charset="UTF-8">
  <title>SQRL Sample Website</title>
</head>
<body>
<h1>Welcome to your SQRL test site</h1>

<p>Login with SQRL or create a new account.</p>

<?php
include_once 'init.php';
$sqrl = new \JurgenhaasRamriot\SQRL\Sample\SQRL(FALSE);
print $sqrl->get_markup('login');
?>

</body>
</html>

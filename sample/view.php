<?php

if (empty($_GET['operation'])) {
  exit;
}
include_once 'init.php';

$sqrl = new \GMJH\SQRL\Sample\SQRL(FALSE);
?>
<!DOCTYPE html>
<html>
<head lang="en">
  <meta charset="UTF-8">
  <title>SQRL Sample Website | Login without JavaScript</title>
</head>
<body>
<h1>Login with SQRL but without JavaScript</h1>

<?php print $sqrl->get_markup($_GET['operation'], TRUE); ?>

</body>
</html>

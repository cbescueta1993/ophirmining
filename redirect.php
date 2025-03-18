<?php
$input = file_get_contents("php://input");
file_put_contents("alertlog.txt", $input . PHP_EOL, FILE_APPEND);

//$input='XRPUSDT;BUY;0.03;0.01';
header("Location: createorder.php?input=".$input);
exit;
?>
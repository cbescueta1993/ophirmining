<?php
$input = file_get_contents("php://input");
file_put_contents("alertlog.txt", $input . PHP_EOL, FILE_APPEND);
header("Content-Type: text/plain; charset=utf-8");

echo $input;
?>
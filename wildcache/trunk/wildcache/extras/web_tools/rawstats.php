<?php

header("Content-Type: text/plain");
echo "B\tC\tN\tD\tP\tH\r\n";
echo "------------------------------------------------\r\n";
echo (int) apc_fetch('bypassed')  ."\t";
echo (int) apc_fetch('cached')    ."\t";
echo (int) apc_fetch('noncached') ."\t";
echo (int) apc_fetch('deletes')   ."\t";
echo (int) apc_fetch('puts')      ."\t";
echo (int) apc_fetch('heads')     .chr(10);

?>
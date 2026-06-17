<?php
$start = microtime(true);
require 'config.php';
$end = microtime(true);
echo "Took " . ($end - $start) . " seconds\n";

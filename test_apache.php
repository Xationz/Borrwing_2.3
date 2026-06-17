<?php
$start = microtime(true);
require 'config.php';
$end = microtime(true);
echo "Took " . ($end - $start) . " seconds\n";
echo "Config that succeeded:\n";
// Let's print out the error messages to see what failed
echo implode("\n", $error_messages);

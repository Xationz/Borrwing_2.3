<?php
// Legacy redirect — all pages now use spa_shell.php
session_start();
header('Location: spa_shell.php?page=dashboard');
exit;

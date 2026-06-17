<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=8889;charset=utf8mb4', 'root', 'root');
$pdo->exec("DROP DATABASE IF EXISTS `equipment_borrowing`");
$pdo->exec("CREATE DATABASE `equipment_borrowing` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
$pdo->exec("USE `equipment_borrowing`");

$sql = file_get_contents('Database/127_0_0_1 (3).sql');

$start = microtime(true);
$pdo->exec($sql);
$end = microtime(true);
echo "Full exec took " . ($end - $start) . " seconds\n";

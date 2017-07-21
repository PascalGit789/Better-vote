<?php

$host = 'localhost';
$db   = 'better_vote';
$user = 'root';
$pass = 'firstDroplet*15_43';
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$PDO = new PDO($dsn, $user, $pass, $opt);


date_default_timezone_set('America/New_York');
?>	


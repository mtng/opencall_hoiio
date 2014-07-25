<?php

require_once(__DIR__ . '/../../app/autoload.php');

use Hoiio\Record;
use Symfony\Component\Yaml\Parser;

$yaml = new Parser();
$config = $yaml->parse(file_get_contents(__DIR__ . '/../../app/config/hoiio.yml'));

// setup mysql
$dsn = 'mysql:host=' . $config['database']['host'] . ';dbname=' . $config['database']['db_name'];
$user = $config['database']['user'];
$pass = $config['database']['pass'];

$hoiioHangupURL = $config['url']['hoiio_hangup'];
$hoiioAppID = $config['hoiio_id']['app_id'];
$hoiioAccessToken = $config['hoiio_id']['access_token'];

$pdo = new PDO($dsn, $user, $pass);
$rec = new Record($pdo);
$rec->run($_POST, $hoiioHangupURL, $hoiioAppID, $hoiioAccessToken);

// error_log(print_r($_POST, true));

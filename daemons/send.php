<?php

require_once(__DIR__ . '/../app/autoload.php');

use OnCall\QueueHandler,
    OnCall\QueueMessage,
    Predis\Client;

// setup redis
$redis = new Client();

// sample $_POST emulation
$data = array(
    'sample' => 'test',
    '1' => '1233409',
    'number' => 123,
    'float' => 213.00
);

$msg = new QueueMessage();
$msg->setParams($data);

$sender = new QueueHandler($redis, 'plivo_in');
$sender->send($msg);

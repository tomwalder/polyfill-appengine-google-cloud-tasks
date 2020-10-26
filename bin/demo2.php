<?php

require_once __DIR__ . '/../vendor/autoload.php';

use google\appengine\api\taskqueue\CloudTasksQueue;
use google\appengine\api\taskqueue\PushTask;

// Credentials, Location
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/creds.json');
CloudTasksQueue::initLocation('europe-west2');
CloudTasksQueue::ensureQueues(true);

try {
    $task1 = new PushTask('/someUrlDirectAdd');
    $name = $task1->add('missing');
    echo 'Added: ', $name, PHP_EOL;
} catch (\Exception $obj_ex) {
    echo "Failed with: ", get_class($obj_ex), ' / ', $obj_ex->getMessage(), PHP_EOL;
    echo $obj_ex->getTraceAsString();
}
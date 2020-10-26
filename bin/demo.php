<?php

require_once __DIR__ . '/../vendor/autoload.php';

use google\appengine\api\taskqueue\CloudTasksQueue;
use google\appengine\api\taskqueue\PushTask;
use google\appengine\api\taskqueue\PushQueue;

// Credentials, Location
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/creds.json');
CloudTasksQueue::initLocation('europe-west2');

try {
    $task1 = new PushTask('/someUrl1');
    $task2 = new PushTask('/someUrl2', ['data' => 'value']);
    $task3 = new PushTask('/someUrl3', [], ['delay_seconds' => 30]);
    $task4 = new PushTask('/someUrl4', [], ['header' => 'X-Test: SomeValue']);
    $task5 = new PushTask('/someUrl5', [], ['name' => 'my-awesome-task']);
    $queue = new PushQueue('default');
    $names = $queue->addTasks([$task1, $task2, $task3, $task4, $task5]);
    print_r($names);
} catch (\Exception $obj_ex) {
    echo "Failed with: ", get_class($obj_ex), ' / ', $obj_ex->getMessage(), PHP_EOL;
    echo $obj_ex->getTraceAsString();
}
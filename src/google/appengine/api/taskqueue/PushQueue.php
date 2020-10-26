<?php

namespace google\appengine\api\taskqueue;

/**
 * PushQueue Polyfill
 *
 * Some code Copyright 2007 Google Inc.
 * https://github.com/GoogleCloudPlatform/appengine-php-sdk
 *
 * @package google\appengine\api\taskqueue
 */
class PushQueue {

    /**
     * The maximum number of tasks in a single call addTasks.
     */
    const MAX_TASKS_PER_ADD = 100;

    private $name;

    /**
     * Construct a PushQueue
     *
     * @param string $name The name of the queue.
     */
    public function __construct($name = 'default') {
        if (!is_string($name)) {
            throw new \InvalidArgumentException(
                '$name must be a string. Actual type: ' . gettype($name));
        }
        # TODO: validate queue name length and regex.
        $this->name = $name;
    }

    /**
     * Return the queue's name.
     *
     * @return string The queue's name.
     */
    public function getName() {
        return $this->name;
    }

//    private static function errorCodeToException($error) {
//        switch($error) {
//            case ErrorCode::UNKNOWN_QUEUE:
//                return new TaskQueueException('Unknown queue');
//            case ErrorCode::TRANSIENT_ERROR:
//                return new TransientTaskQueueException();
//            case ErrorCode::INTERNAL_ERROR:
//                return new TaskQueueException('Internal error');
//            case ErrorCode::TASK_TOO_LARGE:
//                return new TaskQueueException('Task too large');
//            case ErrorCode::INVALID_TASK_NAME:
//                return new TaskQueueException('Invalid task name');
//            case ErrorCode::INVALID_QUEUE_NAME:
//            case ErrorCode::TOMBSTONED_QUEUE:
//                return new TaskQueueException('Invalid queue name');
//            case ErrorCode::INVALID_URL:
//                return new TaskQueueException('Invalid URL');
//            case ErrorCode::PERMISSION_DENIED:
//                return new TaskQueueException('Permission Denied');
//
//            // Both TASK_ALREADY_EXISTS and TOMBSTONED_TASK are translated into the
//            // same exception. This is in keeping with the Java API but different to
//            // the Python API. Knowing that the task is tombstoned isn't particularly
//            // interesting: the main point is that it has already been added.
//            case ErrorCode::TASK_ALREADY_EXISTS:
//            case ErrorCode::TOMBSTONED_TASK:
//                return new TaskAlreadyExistsException();
//            case ErrorCode::INVALID_ETA:
//                return new TaskQueueException('Invalid delay_seconds');
//            case ErrorCode::INVALID_REQUEST:
//                return new TaskQueueException('Invalid request');
//            case ErrorCode::DUPLICATE_TASK_NAME:
//                return new TaskQueueException(
//                    'Duplicate task names in addTasks request.');
//            case ErrorCode::TOO_MANY_TASKS:
//                return new TaskQueueException('Too many tasks in request.');
//            case ErrorCode::INVALID_QUEUE_MODE:
//                return new TaskQueueException('Cannot add a PushTask to a pull queue.');
//            default:
//                return new TaskQueueException('Error Code: ' . $error);
//        }
//    }

    /**
     * Add tasks to the queue.
     *
     * @param PushTask[] $tasks The tasks to be added to the queue.
     *
     * @return string[] An array containing the name of each task added, with the same
     * ordering as $tasks.
     *
     * @throws TaskAlreadyExistsException if a task of the same name already
     * exists in the queue.
     * If this exception is raised, the caller can be guaranteed that all tasks
     * were successfully added either by this call or a previous call. Another way
     * to express it is that, if any task failed to be added for a different
     * reason, a different exception will be thrown.
     * @throws TaskQueueException if there was a problem using the service.
     */
    public function addTasks($tasks) {
        if (!is_array($tasks)) {
            throw new \InvalidArgumentException(
                '$tasks must be an array. Actual type: ' . gettype($tasks));
        }
        if (empty($tasks)) {
            return [];
        }
        if (count($tasks) > self::MAX_TASKS_PER_ADD) {
            throw new \InvalidArgumentException(
                '$tasks must contain at most ' . self::MAX_TASKS_PER_ADD .
                ' tasks. Actual size: ' . count($tasks));
        }

        // Now we're into polyfill territory...
        foreach ($tasks as $task) {
            if (!($task instanceof PushTask)) {
                throw new \InvalidArgumentException(
                    'All values in $tasks must be instances of PushTask. ' .
                    'Actual type: ' . gettype($task));
            }
        }
        try {
            $obj_cloud_task_mapper = new CloudTasksQueue($this->name);
            return $obj_cloud_task_mapper->addTasks($tasks);
        } catch (\Google\ApiCore\ApiException $obj_api_exception) {
            throw new TaskQueueException(
                $obj_api_exception->getMessage(),
                $obj_api_exception->getCode(),
                $obj_api_exception
            );
        }

    }
}
<?php

namespace google\appengine\api\taskqueue;

use Google\Auth\CredentialsLoader;
use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\Task;

class CloudTasksQueue {

    /**
     * @var bool
     */
    protected static $bol_ensure_queue = false;

    /**
     * @var string[]
     */
    protected static $arr_ensured_queues = [];

    /**
     * @var string
     */
    protected static $str_project_id;

    /**
     * @var string
     */
    protected static $str_location_id;

    /**
     * @var string
     */
    protected static $str_http_target;

    /**
     * @var CloudTasksClient
     */
    protected static $obj_cloud_tasks_client;

    /**
     * @var CloudTasksClient
     */
    protected $obj_client;

    /**
     * @var string
     */
    protected $str_fq_queue_name;

    /**
     * Queue name, component only.
     *
     * @var string
     */
    protected $str_queue_name;

    /**
     * Ensure we know how to address Cloud Tasks.
     *
     * Detect Project where possible
     */
    public static function init() {
        if (empty(self::$str_project_id)) {
            if (class_exists(CredentialsLoader::class)) {
                $obj_creds = CredentialsLoader::fromEnv();
                if (empty($obj_creds)) {
                    $obj_creds = CredentialsLoader::fromWellKnownFile();
                }
                if (is_array($obj_creds) && isset($obj_creds['project_id'])) {
                    self::$str_project_id = $obj_creds['project_id'];
                }
            }
        }

        if (empty(self::$str_project_id)) {
            throw new \RuntimeException("Cloud Tasks project_id not set.");
        }

        if (empty(self::$str_location_id)) {
            throw new \RuntimeException("Cloud Tasks location_id not set.");
        }
    }

    /**
     * Set the queue Project ID
     *
     * @param string $str_project_id
     */
    public static function initProject($str_project_id) {
        self::$str_project_id = $str_project_id;
    }

    /**
     * Set the queue Location ID
     *
     * @param string $str_location_id
     */
    public static function initLocation($str_location_id) {
        self::$str_location_id = $str_location_id;
    }

    /**
     * Set the base URL for HTTP tasks (non-AppEngine)
     *
     * @param string $str_http_target
     */
    public static function initHttpTarget($str_http_target) {
        self::$str_http_target = $str_http_target;
    }

    /**
     * Pass in a CloudTasksClient, usually pre-configured with auth
     *
     * @param CloudTasksClient $obj_client
     */
    public static function initClient(CloudTasksClient $obj_client) {
        self::$obj_cloud_tasks_client = $obj_client;
    }

    /**
     * Ensure queues exist before adding tasks
     *
     * @param bool $bol_ensure
     */
    public static function ensureQueues($bol_ensure = true) {
        self::$bol_ensure_queue = $bol_ensure;
    }

    /**
     * CloudTasksMapper constructor.
     * @param $str_name
     */
    public function __construct($str_name) {
        self::init();
        $this->str_queue_name = $str_name;
        $this->str_fq_queue_name = CloudTasksClient::queueName(
            self::$str_project_id,
            self::$str_location_id,
            $str_name
        );

        // Client - either use the one provided, or create a new one
        if (empty(self::$obj_cloud_tasks_client)) {
            $this->obj_client = new CloudTasksClient();
        } else {
            $this->obj_client = self::$obj_cloud_tasks_client;
        }
    }

    /**
     * @param PushTask[] $arr_tasks
     */
    public function addTasks(array $arr_tasks) {
        $int_base_eta = time();
        $arr_names = [];
        foreach ($arr_tasks as $obj_task) {
            $arr_names[] = $this->addTask($obj_task, $int_base_eta);
        }
        return $arr_names;
    }

    /**
     * Enqueue a single task
     *
     * @param PushTask $obj_push_task
     * @param int|null $int_base_eta
     * @return string
     * @throws \Google\ApiCore\ApiException
     */
    public function addTask(PushTask $obj_push_task, $int_base_eta = null) {
        if (self::$bol_ensure_queue) {
            $this->ensureQueue();
        }

        if (null === $int_base_eta) {
            $int_base_eta = time();
        }

        // Create a Cloud Task object.
        $obj_cloud_task = new Task();

        // Build the main task payload
        if ($this->useVanillaHttpTasks()) {
            $obj_cloud_task->setHttpRequest(
                $this->buildVanillaHttpTask($obj_push_task)
            );
        } else {
            $obj_cloud_task->setAppEngineHttpRequest(
                $this->buildAppEngineTask($obj_push_task)
            );
        }

        // Task has a set name?
        if ($obj_push_task->hasName()) {
            $obj_cloud_task->setName(
                CloudTasksClient::taskName(
                    self::$str_project_id,
                    self::$str_location_id,
                    $this->str_queue_name,
                    $obj_push_task->getName()
                )
            );
        }

        // Schedule time (default to ~nowish)
        $obj_ts = new \Google\Protobuf\Timestamp();
        $obj_ts->setSeconds($int_base_eta + $obj_push_task->getDelaySeconds());
        $obj_cloud_task->setScheduleTime($obj_ts);

        // Send request and print the obj_cloud_task name.
        $obj_response = $this->obj_client->createTask($this->str_fq_queue_name, $obj_cloud_task);
        return $obj_response->getName();
    }

    /**
     * Should we use vanilla HTTP tasks - rather than GAE-specific?
     *
     * @return bool
     */
    private function useVanillaHttpTasks() {
        if (!empty(self::$str_http_target)) {
            return true;
        }
        return false;
    }

    /**
     * Build an AppEngine HTTP Task
     *
     * URL is relative for GAE
     *
     * @param PushTask $obj_push_task
     * @return AppEngineHttpRequest
     */
    private function buildAppEngineTask(PushTask $obj_push_task) {
        $obj_gae_http_task = new AppEngineHttpRequest();
        $obj_gae_http_task->setRelativeUri($obj_push_task->getUrl());
        $this->applyMethodHeadersBody($obj_push_task, $obj_gae_http_task);
        return $obj_gae_http_task;
    }

    /**
     * Build a vanilla HTTP push Task.
     *
     * URL is absolute here
     *
     * @todo Consider OAuth / OIDC headers
     *
     * @param PushTask $obj_push_task
     * @return HttpRequest
     */
    private function buildVanillaHttpTask(PushTask $obj_push_task) {
        $obj_http_task = new HttpRequest();
        $obj_http_task->setUrl(self::$str_http_target . $obj_push_task->getUrl());
        $this->applyMethodHeadersBody($obj_push_task, $obj_http_task);
        return $obj_http_task;
    }

    /**
     * Apply common data from the PushTask to the HTTP/GAE task
     *
     * @param PushTask $obj_push_task
     * @param AppEngineHttpRequest|HttpRequest $obj_new_task
     */
    private function applyMethodHeadersBody(PushTask $obj_push_task, $obj_new_task) {

        // HTTP method as held by the PushTask
        $str_method = $obj_push_task->getMethod();
        $obj_new_task->setHttpMethod(
            constant(HttpMethod::class . '::' . $str_method)
        );

        // Headers
        if ($obj_push_task->hasHeaders()) {
            $arr_headers = [];
            foreach ($obj_push_task->getHeaders() as $header) {
                $pair = explode(':', $header, 2);
                $arr_headers[trim($pair[0])] = trim($pair[1]);
            }
            $obj_new_task->setHeaders($arr_headers);
        }

        // Setting a body value is only compatible with HTTP POST and PUT requests.
        if ('POST' === $str_method || 'PUT' === $str_method) {
            if ($obj_push_task->hasQueryData()) {
                $obj_new_task->setBody(
                    http_build_query(
                        $obj_push_task->getQueryData()
                    )
                );
            }
        }
    }

    /**
     * Ensure the queue exists
     *
     * Thread cache the result
     */
    protected function ensureQueue() {
        if (isset(self::$arr_ensured_queues[$this->str_fq_queue_name])) {
            return;
        }

        // Attempt to fetch the queue
        $str_fq_location = CloudTasksClient::locationName(
            self::$str_project_id,
            self::$str_location_id
        );
        $obj_queues = $this->obj_client->listQueues($str_fq_location, ['filter' => 'name=' . $this->str_fq_queue_name]);
        /** @var \Google\Cloud\Tasks\V2\Queue $obj_queue */
        foreach ($obj_queues as $obj_queue) {
            if ($this->str_fq_queue_name === $obj_queue->getName()) {
                // Matching queue found
                self::$arr_ensured_queues[$this->str_fq_queue_name] = true;
                return;
            }
        }

        // Create Queue if not exists
        $obj_new_queue = new \Google\Cloud\Tasks\V2\Queue([
            'name' => $this->str_fq_queue_name
        ]);
        $obj_new_queue->setName($this->str_fq_queue_name);
        $this->obj_client->createQueue(
            $str_fq_location,
            $obj_new_queue
        );
    }
}
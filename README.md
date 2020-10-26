# Polyfill for Google AppEngine Tasks to Google Cloud Tasks API

Snazzy title, I know.

If you are doing this on AppEngine PHP 5.5 runtime:

```php
use google\appengine\api\taskqueue\PushTask;
use google\appengine\api\taskqueue\PushQueue;

$task1 = new PushTask('/someUrl');
$task2 = new PushTask('/someOtherUrl');
$queue = new PushQueue();
$queue->addTasks([$task1, $task2]);
```
or this...
```php
use google\appengine\api\taskqueue\PushTask;

$task1 = new PushTask('/someUrl');
$task1->add();
```

Then this library will allow that code to work outside of AppEngine, providing a polyfill to using the new Google Cloud Tasks API.

This is a stepping stone to upgrading to [PHP 7 standard runtime](https://cloud.google.com/appengine/docs/standard/php7), [Flexible runtime](https://cloud.google.com/appengine/docs/flexible/php) - or better still, [Cloud Run](https://cloud.google.com/run).

## Installation & Setup

Pull in the library with Composer
```bash
composer require tomwalder/polyfill-appengine-google-cloud-tasks
```

You'll need to configure the location (region) where your Cloud Tasks queues are. Usually do this once in your bootstrap code.
```php
CloudTasksQueue::initLocation('europe-west2');
```

### Authentication

Your application will need credentials to call the Google Cloud Tasks API. This is usually handled automatically with [Default Credentials](https://github.com/googleapis/google-auth-library-php#application-default-credentials), from GCP instances etc.

If you need to specify the path manually, do something like this...
```php
putenv('GOOGLE_APPLICATION_CREDENTIALS=/path/to/my/credentials.json');
```

Alternatively, you can configure and inject your own `Google\Cloud\Tasks\V2\CloudTasksClient` as follows. See [Google Client Authentication](https://github.com/googleapis/google-cloud-php/blob/master/AUTHENTICATION.md) for information about authenticating.
```php
$obj_client = new CloudTasksClient([
   'keyFilePath' => '/path/to/keyfile.json'
]);
CloudTasksQueue::initClient($obj_client);
```

### Absolute Task URLs (Cloud Run)

If you are using this library on Cloud Run, or GCP instances (i.e. outside of AppEngine), HTTP push task URLs are no longer relative.

So you will need to set the base HTTP target - all your relative task URLs will be appended to this.

```php
CloudTasksQueue::initHttpTarget('https://my-awesome-project-hvdgury43f-ew.a.run.app');
```

## Usage

Essentially, you can do most things using the original Google `PushTask` and `PushQueue` classes. See above for examples.

## Further Information

### Bulk Add

Google Cloud Tasks does not support bulk-adding, so the polyfill adds each task one after the other for you.

You may notice some increase in latency. Also, in rare cases you may get an error part way through the task enqueueing process. This will result in some, but not all, of your tasks being created. 

### Specify Project ID Manually

You can specify the Google Project ID manually as follows (we try and extract from the credentials):
```php
CloudTasksQueue::initProject('my-awesome-project');
```

### Performance

It is strongly recommended to use the `gRPC` and `Protobuf` extensions to get the best performance out of all Google APIs.

The default is `REST` unless the above are available. The Google Auth stack auto-detects.

Those extensions are available in the Google App Engine PHP 7 runtime and can be enabled in php.ini

* [Google App Engine PHP 7 Runtime Extensions](https://cloud.google.com/appengine/docs/standard/php7/runtime#dynamically_loadable_extensions)
* [PHP gRPC Installation](https://cloud.google.com/php/grpc)

### Force Queues

If you want to be able to use new Queue names without worrying about creating the queues yourself, you can enable as follows:
```php
CloudTasksQueue::ensureQueues(true);
```

This will check to see a Queue exists before inserting tasks, and attempt to create it.

This may increase latency and is probably not sensible for production use.

### External Links

* [Google Cloud Tasks PHP Client](https://github.com/googleapis/google-cloud-php-tasks)
* [Google Cloud Tasks API](https://cloud.google.com/tasks/docs/reference/rest)
* [Google Client Authentication](https://github.com/googleapis/google-cloud-php/blob/master/AUTHENTICATION.md)

## Credits

Some parts of this code borrowed from:
https://github.com/GoogleCloudPlatform/appengine-php-sdk
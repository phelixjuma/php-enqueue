PHELIXJUMA PHP-ENQUEUE
=========================

This is a simple but robust implementation of redis-based job queues in PHP.

Why another job queue package? Well, I tried all the top ones I could find but none just fit fine: some of the top suggested options haven't been maintained in over 5 years and their dependencies caused a lot of conflicts with my other packages, so I built a new package, for me.

The backend for this package is redis.

Requirements
============

* PHP >= 7.1
* vlucas/phpdotenv
* predis/predis
* symfony/console
* amphp/parallel
    

Installation
============

```
composer require phelixjuma/php-enqueue
```

# USAGE


## 1.Running the Worker
php-enqueue is event driven. Jobs are dispatched and get scheduled in redis. 
A worker has to be set up to run "forever". This worker listens for any incoming job and executes it.
Job execution is done concurrently using amphp/parallel package which allows for several jobs to be executed concurrently 

To set up a worker, run the command below:
```
./bin/worker --queue=name_of_queue --threaded=1 --concurrency=1 --max_retries=3 --log_path=/path/to/log log_level=100
```

Note that the worker takes parameters such us:
1. queue: The name of the queue the worker listens to. Each worker can only listen to a single queue. This allows you to have multiple workers handling different queues which introduces a level of parallelization in your job execution
2. threaded: Value of 1 means the jobs will be run in multi-threaded manner (non blocking). 0 means the jobs are run in a blocking manner. Use multi-threaded if your jobs do not depend on any global variables, otherwise, set it to 0 (blocking execution)
3. concurrency: Defines the number of concurrent jobs a single worker can handle at a given time
4. max_retries: If a job fails, typically by throwing an exception, it will be retried to a max number of times defined here. By default, no retry is done
5. log_path: The path to the directory where logs should be put. Specify a directory path not a log file and ensure php has permissions to write to that directory
6. log_level: As per the monolog log levels

NB: 
1. You can use a service like supervisord to run the workers and to watch them so that, if the worker itself fails, it can be automatically restarted.
2. When you update your codebase on any part the workers rely on, it is good to note that the updates will not reflect unless the worker is restarted

## 2. Manage Jobs
You have options to manage tasks in the command line.

### 2.1 View list of jobs
```
./bin/manager enqueue:list --queue=queue_name
```
### 2.2 Add new Job
```
./bin/manager enqueue:add --queue=queue_name --class=job_class_name --parameters=job_args
```

### 2.3 Remove a job from queue
```
./bin/manager enqueue:remove --queue=queue_name --taskId=task_id
```

## 3. Jobs

Every job class implement JobInterface. The classes to define are: 
- setUp() - for any setup needed before the job is executed, 
- perform() - for actual logic to run for the job and 
- tearDown() - for any post-processing tasks to do.

### 3.1 Sample Job Class

```php
class EmailJob implements JobInterface
{

    /**
     * @var Logger
     */
    private $logger;

    public function setUp(Task $task)
    {
        $this->logger = new Logger("email_job");
        $this->logger->pushHandler(new StreamHandler('/path/to/log/email_job.log', Logger::DEBUG));
    }

    public function perform(Task $task)
    {
        // Actual logic to send an email goes here
        $this->logger->info("Performing email job. Args: ".json_encode($task->getArgs()));
    }

    public function tearDown(Task $task)
    {
    }
}
```

### 3.2 Script to schedule the job
```php
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\Task;
use Predis\Client;

// Some global section where redis and queue are defined
$redis = new Client('tcp://127.0.0.1:6379');
$queue = new RedisQueue($redis);

// Actual section to queue the email job task
$queue
->setName('test_queue')
->enqueue(new Task(new EmailJob(), ['some_arg' => 'some_value']));

```

NB: A better approach would be to have an enqueue service that wraps php-enqueue. In this service, you define the redis and queue so they are reusabe. An example of such a service is as shown below
```php
<?php

namespace \Some\Name\Space\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\Task;
use Predis\Client;

final class EnqueueService {

    const DEFAULT_QUEUE = 'default';

    private static $instance = null;
    private RedisQueue|null $queue = null;

    /**
     * @return EnqueueService|null
     */
    private static function getInstance(): ?EnqueueService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param $job
     * @param $args
     * @param string $queueName
     * @return void
     */
    public static function enqueue($job, $args, string $queueName=self::DEFAULT_QUEUE): void
    {
        self::getInstance()
            ->init()
            ->queue
            ->setName($queueName)
            ->enqueue(new Task($job, $args));;
    }

    /**
     * @return $this
     */
    private function init(): EnqueueService
    {
        if ($this->queue !== null) {
            return $this;
        }

        $redisHost = getenv("REDIS_HOST");
        $redisPort = getenv("REDIS_PORT");

        $redis = new Client("tcp://$redisHost:$redisPort");

        $this->queue = new RedisQueue($redis);

        return $this;
    }

}

// Use the service within your application to add a job to a queue as
EnqueueService::enqueue(new EmailJob(), ['key' => 'value']);
```

## 4. A note On Jobs
- This package uses Redis to enqueue jobs. 
- This means that the job alongside its arguments are serialized before queueing and unserialized when fetched 
- Because of how PHP handles serialization/unserialization, your Jobs and args should not have instances that are not serializable
- A good example of something that's unserializable is pdo. So if your job class injects a database class with an instance of pdo, then the job queueing will fail
- A good practice is to not inject any classes in the Job class and to avoid using class instances as arguments
- Instead, use the setUp() method alongside your DI container to instantiate any other classes you need.

Credits
=======

* Phelix Juma  (jp@docusift.ai)

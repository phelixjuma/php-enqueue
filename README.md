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
./bin/worker --queue=name_of_queue --concurrency=1 --max_retries=3 --log_path=/path/to/log log_level=100 --project_root=/path/to/project/root
```

Note that the worker takes parameters such us:
1. queue: The name of the queue the worker listens to. Each worker can only listen to a single queue. This allows you to have multiple workers handling different queues which introduces a level of parallelization in your job execution
2. concurrency: Defines the number of concurrent jobs a single worker can handle at a given time
3. max_retries: If a job fails, typically by throwing an exception, it will be retried to a max number of times defined here. By default, no retry is done
4. log_path: The path to the directory where logs should be put. Specify a directory path not a log file and ensure php has permissions to write to that directory
5. log_level: As per the monolog log levels

NB: 
1. You can use a service like supervisord to run the workers and to watch them so that, if the worker itself fails, it can be automatically restarted.
2. When you update your codebase on any part the workers rely on, it is good to note that the updates will not reflect unless the worker is restarted

## 2. Manage Jobs
You have options to manage tasks in the command line.

### 2.1 View list of jobs
```
./bin/manager enqueue:list --queue=queue_name --project_root=/path/to/project/root
```
### 2.2 Add new Job
```
./bin/manager enqueue:add --queue=queue_name --class=job_class_name --parameters=job_args --project_root=/path/to/project/root
```

### 2.3 Remove a job from queue
```
./bin/manager enqueue:remove --queue=queue_name --taskId=task_id --project_root=/path/to/project/root
```

## 3. Jobs

Every job class must define the three methods: 
- setUp - for any setup needed before the job is executed, 
- perform() - for actual logic to run for the job and 
- tearDown() - for any post-processing tasks to do.

### 3.1 Sample Job Class

```php
class EmailJob
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

Credits
=======

* Phelix Juma  (jp@docusift.ai)

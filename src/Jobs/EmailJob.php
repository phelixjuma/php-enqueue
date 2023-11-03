<?php

namespace Phelixjuma\Enqueue\Jobs;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pheanstalk\Values\Job;
use Phelixjuma\Enqueue\BeanstalkdQueue;
use Phelixjuma\Enqueue\JobInterface;
use Phelixjuma\Enqueue\QueueInterface;
use Phelixjuma\Enqueue\Task;

class EmailJob implements JobInterface
{

    /**
     * @var Logger
     */
    private $logger;

    public function setUp(Task $task)
    {
        $this->logger = new Logger("email_job");
        $this->logger->pushHandler(new StreamHandler('/usr/local/var/www/php-enqueue/log/email_job.log', Logger::DEBUG));
    }

    public function perform(Task $task, QueueInterface $queue=null, Job $job=null)
    {
        sleep(2);

        if ($queue instanceof BeanstalkdQueue) {
            $queue->getClient()->touch($job);
            $this->logger->info("touching job");
        }

        sleep(2);

        $this->logger->info("Completed. Args: ".json_encode($task->getArgs()));
    }

    public function tearDown(Task $task)
    {
    }
}


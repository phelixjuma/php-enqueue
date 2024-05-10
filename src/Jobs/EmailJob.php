<?php

namespace Phelixjuma\Enqueue\Jobs;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pheanstalk\Values\Job;
use Phelixjuma\Enqueue\BeanstalkdQueue;
use Phelixjuma\Enqueue\JobInterface;
use Phelixjuma\Enqueue\QueueInterface;
use Phelixjuma\Enqueue\RepeatTask;
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

    /**
     * @param Task $task
     * @return void
     * @throws Exception
     */
    public function perform(Task $task)
    {
        $now = date("Y-m-d H:i:s", time());

        // Get next run
        $schedule = $task->getSchedule();
        $nextRun = null;
        if(!empty($schedule)) {
            $schedule->calculateNextRun();
            $nextRun = $schedule->nextRun;
        }

        $this->logger->info("Completed at $now and next run is at {$nextRun}. Args: ".json_encode($task->getArgs()));
    }

    public function tearDown(Task $task)
    {
    }
}


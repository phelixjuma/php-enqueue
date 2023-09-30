<?php

namespace Phelixjuma\Enqueue\Jobs;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phelixjuma\Enqueue\JobInterface;
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

    public function perform(Task $task)
    {
        $this->logger->info("Performing email job. Args: ".json_encode($task->getArgs()));
    }

    public function tearDown(Task $task)
    {
    }
}


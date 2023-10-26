<?php

namespace Phelixjuma\Enqueue;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use function Amp\Parallel\Worker\createWorker;

class Worker
{
    private RedisQueue $queue;
    private int $concurrency;
    private int $maxRetries;
    private $threaded;
    private $maxTime;
    private $maxJobs;
    private LoggerInterface $logger;

    /**
     * @param RedisQueue $queue
     * @param $threaded
     * @param $concurrency
     * @param $maxRetries
     * @param $maxTime
     * @param $maxJobs
     * @param LoggerInterface $logger
     */
    public function __construct(RedisQueue $queue, $threaded, $concurrency, $maxRetries, $maxTime, $maxJobs, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->concurrency = $concurrency;
        $this->maxRetries = $maxRetries;
        $this->threaded = $threaded;
        $this->maxTime = $maxTime;
        $this->maxJobs = $maxJobs;
    }

    /**
     * @return void
     */
    public function start()
    {
        $startTime = time();
        $doneJobs = 0;

        while (true) {

            try {

                $task = $this->queue->fetch();

                if (!empty($task)) {

                    if ($task instanceof Task || $task instanceof Event) {

                        $task->setStatus('processing');

                        $task->execute($this->queue, $this->logger, $this->maxRetries);

                        // Increment jobs count
                        $doneJobs++;

                    } else {
                        // We fail invalid tasks
                        $this->queue->fail($task);
                    }
                }

            } catch (\Exception | \Throwable  $e) {
                $this->logger->error($e->getMessage());
            }

            // Check if max time is set
            if (!empty($this->maxTime) && $this->maxTime > 0) {

                if (((time() - $startTime)/1000) > $this->maxTime) {
                    $this->logger->error("Worker is exiting due to max time reached");
                    exit();
                }
            }

            // Check if max jobs is set
            if (!empty($this->maxJobs) && $this->maxJobs > 0) {

                if ($doneJobs > $this->maxJobs) {
                    $this->logger->error("Worker is exiting due to max number of jobs reached");
                    exit();
                }
            }

            // sleep for 0.1 second before proceeding.
            usleep(100000);
        }
    }

}

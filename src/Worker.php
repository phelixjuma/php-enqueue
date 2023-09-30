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
    private LoggerInterface $logger;

    /**
     * @param RedisQueue $queue
     * @param $threaded
     * @param $concurrency
     * @param $maxRetries
     * @param LoggerInterface $logger
     */
    public function __construct(RedisQueue $queue, $threaded, $concurrency, $maxRetries, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->concurrency = $concurrency;
        $this->maxRetries = $maxRetries;
        $this->threaded = $threaded;
    }

    /**
     * @return void
     */
    public function start()
    {
        while (true) {

            try {

                $task = $this->queue->fetch();

                if (!empty($task)) {

                    if ($task instanceof Task || $task instanceof Event) {

                        $task->setStatus('processing');

                        $task->execute($this->queue, $this->logger, $this->maxRetries);

                    } else {
                        // We fail invalid tasks
                        $this->queue->fail($task);
                    }
                }

            } catch (\Exception | \Throwable  $e) {
                $this->logger->error($e->getMessage());
            }

            usleep(100000); // sleep for 0.1 second
        }
    }

}

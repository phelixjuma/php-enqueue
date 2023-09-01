<?php

namespace Phelixjuma\Enqueue;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use function Amp\Parallel\Worker\createWorker;

class Worker
{
    private RedisQueue $queue;
    private EventDispatcherInterface $dispatcher;
    private int $concurrency;
    private int $maxRetries;
    private LoggerInterface $logger;

    /**
     * @param RedisQueue $queue
     * @param $concurrency
     * @param $maxRetries
     * @param EventDispatcherInterface $dispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(RedisQueue $queue, $concurrency, $maxRetries, EventDispatcherInterface $dispatcher, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->concurrency = $concurrency;
        $this->maxRetries = $maxRetries;
    }

    public function start()
    {
        $this->dispatcher->dispatch(new WorkerEvent($this), 'worker.start');
        $this->logger->info('Worker started');

        while (true) {

            $task = $this->queue->fetch();

            if ($task instanceof Task) {

                $this->dispatcher->dispatch(new TaskEvent($task), 'task.fetched');
                $this->logger->info('Task fetched');

                $task->setStatus('processing');

                $worker = createWorker();
                $parallelTask = new TaskHandler($this->queue, $task, $this->logger, $this->dispatcher, $this->maxRetries);

                $execution = $worker->submit($parallelTask);
            }

            usleep(100000); // sleep for 0.1 seconds
        }

        $pool->shutdown();

        $this->dispatcher->dispatch(new WorkerEvent($this), 'worker.finished');
        $this->logger->info('Worker Finished');
    }
}

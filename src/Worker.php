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
    private $threaded;
    private LoggerInterface $logger;

    /**
     * @param RedisQueue $queue
     * @param $threaded
     * @param $concurrency
     * @param $maxRetries
     * @param EventDispatcherInterface $dispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(RedisQueue $queue, $threaded, $concurrency, $maxRetries, EventDispatcherInterface $dispatcher, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->dispatcher = $dispatcher;
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
        $this->dispatcher->dispatch(new WorkerEvent($this), 'worker.start');

        while (true) {

            try {

                $task = $this->queue->fetch();

                if (!empty($task)) {

                    if ($task instanceof Task) {

                        $this->dispatcher->dispatch(new TaskEvent($task), 'task.fetched');

                        $task->setStatus('processing');

                        if ($this->threaded == 1) {

                            /**
                             * Option 1: Use this for non-blocking execution.
                             * Ideal only if your Jobs do not depend on other global variables wirthin the application
                             */
                            $worker = createWorker();
                            $parallelTask = new TaskHandler($this->queue, $task, $this->logger, $this->dispatcher, $this->maxRetries);
                            $worker->submit($parallelTask);

                        } else {

                            /**
                             * Option 2: Use this for blocking execution
                             * Ideal for cases where the Jobs depend on global variables
                             */
                            $task->execute($this->queue, $this->logger, $this->dispatcher, $this->maxRetries);

                        }

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

        $this->dispatcher->dispatch(new WorkerEvent($this), 'worker.finished');
    }

}

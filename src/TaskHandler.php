<?php

namespace Phelixjuma\Enqueue;

use Amp\Cancellation;
use Amp\Sync\Channel;
use Amp\Parallel\Worker\Task as ParallelTask;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TaskHandler implements ParallelTask
{
    private Task $task;
    private LoggerInterface $logger;
    private EventDispatcherInterface $dispatcher;
    private RedisQueue $queue;
    private $maxRetries;

    /**
     * @param RedisQueue $queue
     * @param Task $task
     * @param LoggerInterface $logger
     * @param EventDispatcherInterface $dispatcher
     * @param $maxRetries
     */
    public function __construct(RedisQueue $queue, Task $task, LoggerInterface $logger, EventDispatcherInterface $dispatcher, $maxRetries=1)
    {
        $this->task = $task;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->queue = $queue;
        $this->maxRetries = $maxRetries;
    }

    /**
     * @param Channel $channel
     * @param Cancellation $cancellation
     * @return string
     */
    public function run(Channel $channel, Cancellation $cancellation): string
    {
        return $this->task->execute($this->queue, $this->logger, $this->dispatcher, $this->maxRetries);
    }
}

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

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $this->logger->info('Running task');

        $job = $this->task->getJob();

        try {

            $this->logger->info('Executing task');

            $job->setUp($this->task);
            $response = $job->perform($this->task);
            $job->tearDown($this->task);

            $this->logger->info('Completed task with response: '.(!empty($response) ? json_encode($response): ""));

            $this->task->setStatus('completed');

            $this->dispatcher->dispatch(new TaskEvent($this->task), 'task.completed');

        } catch (\Exception | \Throwable  $e) {

            $this->logger->error('Failed with error', ['error' => $e->getMessage()]);

            $this->task->setStatus('failed');

            $this->dispatcher->dispatch(new TaskEvent($this->task), 'task.failed');

            $retries = $this->task->getRetries();

            if ($retries < $this->maxRetries) {

                $this->task->setRetries($retries + 1);

                // Requeue the task
                $this->queue->enqueue($this->task);

                $this->logger->info('Task requeued', ['retries' => $retries + 1]);

            } else {
                $this->logger->info('Task retries exhausted', ['retries' => $retries]);
            }

        }

        $this->logger->info('Execution Status '.$this->task->getStatus());

        return $this->task->getStatus();
    }
}

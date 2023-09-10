<?php
namespace Phelixjuma\Enqueue;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Task
{
    private $job;
    private $args;
    private $status;
    private $retries = 0;
    private $created_at;

    public function __construct($job, $args = null)
    {
        $this->job = $job;
        $this->args = $args;
        $this->status = 'pending';
        $this->created_at = new \DateTime();
    }

    public function getJob()
    {
        return $this->job;
    }

    public function getArgs()
    {
        return $this->args;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getRetries()
    {
        return $this->retries;
    }

    public function setRetries($retries)
    {
        $this->retries = $retries;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * @param RedisQueue $queue
     * @param LoggerInterface $logger
     * @param EventDispatcherInterface $dispatcher
     * @param $maxRetries
     * @return string
     */
    public function execute(RedisQueue $queue, LoggerInterface $logger, EventDispatcherInterface $dispatcher, $maxRetries=1): string
    {

        $jobInstance = $this->getJob();
        $job = clone $jobInstance;

        try {

            $job->setUp($this);

            $job->perform($this);

            $job->tearDown($this);

            $this->setStatus('completed');

            $dispatcher->dispatch(new TaskEvent($this), 'task.completed');

        } catch (\Exception | \Throwable  $e) {

            $logger->error('Failed with error', ['error' => $e->getMessage()]);

            $this->setStatus('failed');

            $dispatcher->dispatch(new TaskEvent($this), 'task.failed');

            $retries = $this->getRetries();

            if ($retries < $maxRetries) {

                $this->setRetries($retries + 1);

                // Requeue the task
                $queue->enqueue($this);

            }
        }

        return $this->getStatus();

    }
}

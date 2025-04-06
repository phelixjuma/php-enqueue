<?php

namespace Phelixjuma\Enqueue;

use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;

class BeanstalkdQueue implements QueueInterface
{
    private Pheanstalk $client;
    private TubeName $queue_name;
    private TubeName $failed_queue_name;

    public function __construct(Pheanstalk $client, $queueName = 'default')
    {
        $this->client = $client;
        $this->queue_name = new TubeName($queueName);;
        $this->failed_queue_name = new TubeName($queueName . '.failed');
    }

    /**
     * @return Pheanstalk
     */
    public function getClient(): Pheanstalk
    {
        return $this->client;
    }

    /**
     * @return TubeName
     */
    public function getQueue(): TubeName
    {
        return $this->queue_name;
    }

    public function setName($name): BeanstalkdQueue
    {
        $this->queue_name = new TubeName($name);;
        $this->failed_queue_name = new TubeName($name . '.failed');

        return $this;
    }

    public function getName() {
        return $this->queue_name;
    }

    /**
     * @param Task $task
     * @return true
     */
    public function enqueue(Task $task): bool
    {
        $this->client->useTube($this->queue_name);

        $this->client->put(
            serialize($task), // data
            $task->getPriority(), // priority
            $task->getDelay(), // delay
            $task->getTimeToRelease() // time to release
        );

        return true;
    }

    /**
     * @param Task $task
     * @return false
     */
    public function fail(Task $task): bool
    {
        // We add the task to the failed queue
        $this->client->useTube($this->failed_queue_name);

        $this->client->put(
            serialize($task), // data
            $task->getPriority(), // priority
            $task->getDelay(), // delay
            $task->getTimeToRelease() // time to release
        );

        return false;
    }

    public function fetch(): ?Task
    {
        return null;
    }
    
    public function fetchScheduled(): ?Task
    {
        return null;
    }
}

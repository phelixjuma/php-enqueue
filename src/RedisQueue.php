<?php

namespace Phelixjuma\Enqueue;

use Predis\Client;

class RedisQueue
{
    private Client $client;
    private $queue_name;
    private $reserved_queue_name;
    private $failed_queue_name;

    public function __construct(Client $client, $queueName = 'default')
    {
        $this->client = $client;
        $this->queue_name = $queueName;
        $this->reserved_queue_name = $queueName . ':reserved';
        $this->failed_queue_name = $queueName . ':failed';
    }

    public function setName($name): RedisQueue
    {
        $this->queue_name = $name;
        $this->reserved_queue_name = $name . ':reserved';
        $this->failed_queue_name = $name . ':failed';
        return $this;
    }

    public function enqueue(Task $task)
    {
        $this->client->rpush($this->queue_name, [serialize($task),'']);
    }

    public function fetch()
    {
        $serializedTask = $this->client->brpoplpush($this->queue_name, $this->reserved_queue_name, 0);
        return $serializedTask ? unserialize($serializedTask) : null;
    }

    public function acknowledge(Task $task)
    {
        $this->client->lrem($this->reserved_queue_name, 1, serialize($task));
    }

    public function fail(Task $task)
    {
        // Remove from reserved and add to failed queue
        $this->client->lrem($this->reserved_queue_name, 1, serialize($task));
        $this->client->rpush($this->failed_queue_name, [serialize($task), '']);
    }

    public function list(): array
    {
        return array_map(function($data) {
            return unserialize($data);
        }, $this->client->lrange($this->queue_name, 0, -1));
    }

    public function getFailedJobs(): array
    {
        return array_map(function($data) {
            return $data ? unserialize($data) : null;
        }, $this->client->lrange($this->failed_queue_name, 0, -1));
    }

    public function remove(Task $task)
    {
        $this->client->lrem($this->queue_name, 0, serialize($task));
    }

    public function getReservedJobs(): array
    {

        return array_map(function($data) {
            return $data ? unserialize($data) : null;
        }, $this->client->lrange($this->reserved_queue_name, 0, -1));

    }

    /**
     * @param $timeoutInSeconds
     * @return void
     */
    public function handleStuckTasks($timeoutInSeconds)
    {
        $reservedTasks = $this->getReservedJobs();

        $currentTime = time();

        foreach ($reservedTasks as $task) {

            if ($task instanceof Task) {

                if (($task->getCreatedAt() + $timeoutInSeconds) < $currentTime) {

                    // The task has been in the reserved queue for longer than the timeout

                    // Requeue the task
                    $this->enqueue($task);

                    // Remove the task from the reserved queue
                    $this->client->lrem($this->reserved_queue_name, 1, serialize($task));
                }
            }
        }
    }

    /**
     * @return void
     */
    public function requeueFailedJobs()
    {
        $failedJobs = $this->getFailedJobs();

        foreach ($failedJobs as $task) {

            if ($task instanceof Task) {

                // Requeue the task to the main queue
                $this->enqueue($task);

                // Remove the task from the failed queue
                $this->client->lrem($this->failed_queue_name, 1, serialize($task));

            }

        }
    }


}

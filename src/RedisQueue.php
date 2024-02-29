<?php

namespace Phelixjuma\Enqueue;

use Predis\Client;

class RedisQueue implements QueueInterface
{
    private Client $client;
    private $queue_name;
    private string $failed_queue_name;

    public function __construct(Client $client, $queueName = 'default')
    {
        $this->client = $client;
        $this->queue_name = $queueName;
        $this->failed_queue_name = $queueName . '.failed';
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name): RedisQueue
    {
        $this->queue_name = $name;
        $this->failed_queue_name = $name . '.failed';
        return $this;
    }

    public function getName() {
        return $this->queue_name;
    }

    /**
     * @param Task $task
     * @return void
     */
    public function enqueue(Task $task)
    {
        $this->client->rpush($this->queue_name, [serialize($task),'']);
    }

    public function fail(Task $task)
    {
        // We add the task to the failed queue
        $this->client->rpush($this->failed_queue_name, [serialize($task), '']);
    }

    public function fetch()
    {
        $serializedTask = $this->client->lpop($this->queue_name);

        return $serializedTask ? unserialize($serializedTask) : null;
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

    public function removeFromQueue(Task $task)
    {
        $this->client->lrem($this->queue_name, 0, serialize($task));
    }

    public function removeFromFailedQueue(Task $task)
    {
        $this->client->lrem($this->failed_queue_name, 0, serialize($task));
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

    /**
     * @return void
     */
    public function removeAllFailedJobs()
    {
        $failedJobs = $this->getFailedJobs();

        foreach ($failedJobs as $task) {
            $this->removeFromFailedQueue($task);

        }
    }

}

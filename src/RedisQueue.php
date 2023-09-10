<?php
namespace Phelixjuma\Enqueue;

use Predis\Client;

class RedisQueue
{
    private Client $client;
    private $queue_name;

    public function __construct(Client $client, $queueName = 'default')
    {
        $this->client = $client;
        $this->queue_name = $queueName;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name): RedisQueue
    {

        $this->queue_name = $name;

        return $this;
    }

    public function enqueue(Task $task)
    {
        $this->client->rpush($this->queue_name, [serialize($task), '']);
    }

    public function dequeue()
    {
        $serializedTask = $this->client->lpop($this->queue_name);

        if ($serializedTask) {
            return unserialize($serializedTask);
        }

        return null;
    }

    public function fetch()
    {
        // Fetch the next task from the Redis queue
        $taskData = $this->client->lpop($this->queue_name);
        return $taskData ? unserialize($taskData) : null;
    }

    public function add(Task $task)
    {
        // Add a task to the Redis queue
        $this->client->lpush($this->queue_name, [serialize($task)]);
    }

    public function list()
    {
        // List all tasks in the Redis queue
        return array_map(function($data) {
            return unserialize($data);
        }, $this->client->lrange($this->queue_name, 0, -1));
    }

    public function remove(Task $task)
    {
        // Remove a task from the Redis queue
        // This method removes all occurrences of the task in the queue
        $this->client->lrem($this->queue_name, 0, serialize($task));
    }
}

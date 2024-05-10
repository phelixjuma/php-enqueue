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
        if ($task instanceof RepeatTask) {
            $this->enqueueSchedule($task);
        } else {
            $this->enqueueTask($task);
        }
    }

    private function enqueueTask(Task $task) {
        $this->client->rpush($this->queue_name, [serialize($task),'']);
    }

    /**
     * @param RepeatTask $task
     * @return void
     */
    public function enqueueSchedule(RepeatTask $task) {

        // Remove the existing task from Redis if it already exists
        $this->deleteTask($task);

        if (!$task->getSchedule()->isPastDue()) {

            $timestamp = strtotime($task->getSchedule()->nextRun);
            $key = $task->getKey();

            // Add the new or updated task
            $this->client->zadd($this->queue_name, [$key => $timestamp]);
            $this->client->set($key, serialize($task));
        }
    }

    /**
     * @param RepeatTask $task
     * @return void
     */
    public function updateTask(Task $task) {
        // Simply call enqueueSchedule again; it will update the task if it exists
        $this->enqueue($task);
    }

    /**
     * @param Task $task
     * @return void
     */
    public function deleteTask(Task $task) {

        $key = $task->getKey();

        if ($this->client->exists($key)) {

            $this->client->zrem($this->queue_name, $key);

            $this->client->del($key);
        }
    }

    public function fail(Task $task)
    {
        // We add the task to the failed queue
        $this->client->rpush($this->failed_queue_name, [serialize($task), '']);
    }

    public function fetch()
    {
        try {

            $serializedTask = $this->client->lpop($this->queue_name);

            return $serializedTask ? unserialize($serializedTask) : null;

        } catch (\Exception $e) {}
        return null;
    }

    /**
     * @return mixed|null
     */
    public function fetchScheduled() {

        // Fetch the next task that is due
        $now = time();

        try {

            $tasks = $this->client->zrangebyscore($this->queue_name, 0, $now, ['limit' => [0, 1]]);

            if (!empty($tasks)) {
                $key = $tasks[0];
                $serializedTask = $this->client->get($key);
                if ($serializedTask) {
                    $task = unserialize($serializedTask);
                    // Remove the task from the schedule after fetching
                    $this->client->zrem($this->queue_name, $key);
                    return $task;
                }
            }

        } catch (\Exception $e){}

        return null;
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

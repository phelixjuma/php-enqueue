<?php

namespace Phelixjuma\Enqueue;

use Exception;
use Predis\Client;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Phelixjuma\Enqueue\Task;

class RedisQueue implements QueueInterface
{
    private Client $client;
    private Logger $logger;
    private $queue_name;
    private string $failed_queue_name;

    public function __construct(Client $client, Logger $logger, $queueName = 'default')
    {
        $this->client = $client;
        $this->logger = $logger;
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
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue_name;
    }

    /**
     * @return Client
     */
    public function getClient(): Client {
        return $this->client;
    }       

    /**
     * @param Task $task
     * @return bool
     */
    public function enqueue(Task $task): bool
    {
        if ($task instanceof RepeatTask) {
            return $this->enqueueSchedule($task);
        } else {
            return $this->enqueueTask($task);
        }
    }

    /**
     * @param Task $task
     * @return bool
     */
    private function enqueueTask(Task $task): bool
    {
        try {

            $id = $this->client->rpush($this->queue_name, [serialize($task),'']);

            return !empty($id);

        } catch (Exception $e) {
            $this->logger->error("Error enqueueing task: " . $e->getMessage());
        }
        return false;
    }

    /**
     * @param RepeatTask $task
     * @return bool
     * @throws Exception
     */
    public function enqueueSchedule(RepeatTask $task): bool
    {

        // Remove the existing task from Redis if it already exists
        $this->deleteTask($task);

        if (!$task->getSchedule()->isPastDue()) {

            $timestamp = (new \DateTime($task->getSchedule()->nextRun, $task->getSchedule()->timezone))->getTimestamp();

            $key = $task->getKey();

            try {
                // Add the new or updated task
                $this->client->zadd($this->queue_name, [$key => $timestamp]);
                $this->client->set($key, serialize($task));

                return true;

            } catch (Exception $e) {
                $this->logger->error("Error enqueueing schedule task: " . $e->getMessage());
            }
        }
        return false;
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function updateTask(Task $task): bool
    {
        // Simply call enqueueSchedule again; it will update the task if it exists
        return $this->enqueue($task);
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function deleteTask(Task $task): bool
    {

        $key = $task->getKey();

        try {
            if ($this->client->exists($key)) {

                $this->client->zrem($this->queue_name, $key);

                $this->client->del($key);
            }
            return true;
        } catch (Exception $e) {
            $this->logger->error("Error deleting task: " . $e->getMessage());
        }
        return false;
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function fail(Task $task): bool
    {
        // We add the task to the failed queue
        try {
            return $this->client->rpush($this->failed_queue_name, [serialize($task), '']);
        } catch (Exception $e) {
            $this->logger->error("Error enqueueing task: " . $e->getMessage());
        }
        return false;
    }

    /**
     * @return Task|null
     */
    public function fetch(): ?Task
    {
        try {
            $serializedTask = $this->client->lpop($this->queue_name);
            return $serializedTask ? unserialize($serializedTask) : null;
        } catch (Exception $e) {
            $this->logger->error("Error fetching task: " . $e->getMessage());
        }
        return null;
    }

    /**
     * @return Task|null
     * @throws Exception
     */
    public function fetchScheduled(): ?Task
    {
        // Fetch the next task that is due
        $timezone = date_default_timezone_get();
        $now = (new \DateTime('now', new \DateTimeZone($timezone)))->getTimestamp();

        try {

            $tasks = $this->client->zrangebyscore($this->queue_name, 0, $now, ['limit' => [0, 1]]);

            print_r($tasks);

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
        } catch (Exception $e){
            $this->logger->error("Error fetching scheduled task: " . $e->getMessage());
        }

        return null;
    }

    /**
     * @return array
     */
    public function list(): array
    {
        return array_map(function($data) {
            return unserialize($data);
        }, $this->client->lrange($this->queue_name, 0, -1));
    }

    /**
     * @return array
     */
    public function getFailedJobs(): array
    {
        return array_map(function($data) {
            return $data ? unserialize($data) : null;
        }, $this->client->lrange($this->failed_queue_name, 0, -1));
    }

    /**
     * @param Task $task
     * @return void
     */
    public function removeFromQueue(Task $task)
    {
        $this->client->lrem($this->queue_name, 0, serialize($task));
    }

    /**
     * @param Task $task
     * @return void
     */
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

<?php

namespace Phelixjuma\Enqueue;

use Pheanstalk\Values\Job;
use Psr\Log\LoggerInterface;

class Worker
{
    private QueueInterface $queue;
    private int $concurrency;
    private int $maxRetries;
    private $threaded;
    private $maxTime;
    private $maxJobs;
    private LoggerInterface $logger;

    private $pid;
    private bool $shouldTerminate = false;
    private bool $isScheduledQueue = false;

    /**
     * @param QueueInterface $queue
     * @param $maxRetries
     * @param $maxTime
     * @param $maxJobs
     * @param LoggerInterface $logger
     */
    public function __construct(QueueInterface $queue, $maxRetries, $maxTime, $maxJobs, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->maxRetries = $maxRetries;
        $this->maxTime = $maxTime;
        $this->maxJobs = $maxJobs;

        // Set the maximum time in seconds that this script should not surpass
        set_time_limit($this->maxTime);

        $this->pid = getmypid();

        // Enable asynchronous signal handling
        pcntl_async_signals(true);

        // Register signal handlers
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
    }

    /**
     * Signal handler to handle termination signals
     * @param int $signal
     */
    public function handleSignal(int $signal): void
    {
        switch ($signal) {
            case SIGINT:
            case SIGTERM:
                $this->logger->info("Received termination signal, finishing current task...");
                $this->shouldTerminate = true;
                // Terminate immediately
                exit(0);
        }
    }

    /**
     * @return void
     */
    public function start()
    {

        if ($this->queue instanceof BeanstalkdQueue) {
            $this->startBeanstalkdWorker();
        } else {
            $this->startRedisWorker();
        }
    }

    public function setScheduledQueue(bool $isScheduled): self {
        $this->isScheduledQueue = $isScheduled;
        return $this;
    }

    private static function getCurrentMemoryUsagePercent() {
        $meminfo = file_get_contents('/proc/meminfo');
        if (!$meminfo) return null;
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatches);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availMatches);
        if (!$totalMatches || !$availMatches) return null;
        $totalMemory = (int)$totalMatches[1];
        $availableMemory = (int)$availMatches[1];
        return (100 * (($totalMemory - $availableMemory) / $totalMemory));
    }

    /**
     * @return void
     */
    private function startRedisWorker(): void
    {
        $startTime = microtime(true);
        $doneJobs = 0;
        $lastPingTime = time();
        $pingInterval = 30; // Check connection every 30 seconds

        while (true) {

            // memory check
            $memoryUsage = self::getCurrentMemoryUsagePercent();

            if ($memoryUsage > 80) {
                $this->logger->error("Memory limit exceeded: " . $memoryUsage . " bytes. Exiting worker.");
                break;
            }

            $currentTime = time();

            // Periodic connection check
            if ($currentTime - $lastPingTime > $pingInterval) {
                try {
                    $this->queue->getClient()->ping();
                    $lastPingTime = $currentTime;
                } catch (\Exception $e) {
                    $this->logger->error("Lost connection to ValKey: " . $e->getMessage());
                    // Exit worker, let supervisor restart it
                    break;
                }
            }

            try {
                if ($this->isScheduledQueue) {
                    // Handle only scheduled tasks
                    while ($task = $this->queue->fetchScheduled()) {
                        if ($task instanceof RepeatTask) {
                            $task->setStatus(Task::STATUS_PROCESSING);
                            $task->execute($this->queue, $this->logger, $this->maxRetries);
                            $doneJobs++;
                        } else {
                            $this->queue->fail($task);
                        }
                    }
                } else {
                    // Handle only regular tasks
                    while ($task = $this->queue->fetch()) {
                        if ($task instanceof Task || $task instanceof Event) {
                            $task->setProcessId($this->pid);
                            $task->setStatus(Task::STATUS_PROCESSING);
                            $task->execute($this->queue, $this->logger, $this->maxRetries);
                            $doneJobs++;
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error("Error processing task: " . $e->getMessage());
                sleep(5);
            }

            // Check if max time is set
            if (!empty($this->maxTime) && $this->maxTime > 0) {

                if ((microtime(true) - $startTime) > $this->maxTime) {
                    break;
                }
            }

            // Check if max jobs is set
            if (!empty($this->maxJobs) && $this->maxJobs > 0) {

                if ($doneJobs > $this->maxJobs) {
                    break;
                }
            }

            usleep(1000000); // 1 second sleep between checks
        }
    }

    /**
     * @return void
     */
    private function startBeanstalkdWorker(): void
    {

        $startTime = microtime(true);
        $doneJobs = 0;

        // We watch to get jobs from the set queue
        $tube = $this->queue->getQueue();

        while (true) {

            if ($this->shouldTerminate) {
                $this->logger->info("Worker of PID {$this->pid} is shutting down gracefully.");
                break;
            }

            $this->queue->getClient()->watch($tube);

            // this hangs until a Job is produced.
            /**
             * @var Job $job
             */
            $job = $this->queue->getClient()->reserve();

            try {

                // We get the task
                $serializedTask = $job->getData();

                // do work.
                if (!empty($serializedTask)) {

                    $task = unserialize($serializedTask);

                    if ($task instanceof Task || $task instanceof Event) {

                        // Set the process id
                        $task->setProcessId($this->pid);

                        // Set the status
                        $task->setStatus(Task::STATUS_PROCESSING);

                        // Check for signals and handle them
                        //pcntl_signal_dispatch();

                        // Execute
                        $task->execute($this->queue, $this->logger, $this->maxRetries);

                        // Increment jobs count
                        $doneJobs++;

                    } else {
                        // We fail invalid tasks
                        $this->queue->fail($task);
                    }
                }

                // eventually we're done, delete job.
                try {
                    $job = $this->queue->getClient()->peek($job);
                    $this->queue->getClient()->delete($job);
                } catch (\Exception $e) {
                    $this->logger->error("Error deleting job: " . $e->getMessage());
                }

            } catch (\Exception $e) {

                // handle exception.
                $this->logger->error($e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . " Trace: " . $e->getTraceAsString());

                // and let some other worker retry.
                try {
                    $job = $this->queue->getClient()->peek($job);
                    $this->queue->getClient()->release($job);
                } catch (\Exception $e) {
                    $this->logger->error("Error releasing job: " . $e->getMessage());
                }
            }

            // Check if max time is set
            if (!empty($this->maxTime) && $this->maxTime > 0) {

                if ((microtime(true) - $startTime) > $this->maxTime) {
                    break;
                }
            }

            // Check if max jobs is set
            if (!empty($this->maxJobs) && $this->maxJobs > 0) {

                if ($doneJobs > $this->maxJobs) {
                    break;
                }
            }

            // sleep for 1 second before proceeding.
            usleep(1000000);
        }

    }

}

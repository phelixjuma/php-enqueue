<?php
namespace Phelixjuma\Enqueue;

use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerInterface;

class Task
{

    private $job;
    private $args;
    protected $listenerDirectory;
    protected $listenerNamespace;
    private $status;
    private $retries = 0;
    private $created_at;

    private $delay;
    private $timeToRelease;
    private $priority;

    /**
     * @param $job
     * @param $args
     * @param int $delay
     * @param int $timeToRelease
     * @param int $priority
     * @param $listenerDir
     * @param $listenerNamespace
     */
    public function __construct($job, $args = null, int $delay=Pheanstalk::DEFAULT_DELAY, int $timeToRelease=Pheanstalk::DEFAULT_TTR, int $priority=Pheanstalk::DEFAULT_PRIORITY, $listenerDir = null, $listenerNamespace=null)
    {
        $this->job = $job;
        $this->args = $args;
        $this->status = 'pending';
        $this->listenerDirectory = $listenerDir;
        $this->listenerNamespace = $listenerNamespace;
        $this->created_at = new \DateTime();
        $this->delay = $delay;
        $this->timeToRelease = $timeToRelease;
        $this->priority = $priority;
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

    public function getDelay()
    {
        return $this->delay;
    }

    public function getTimeToRelease()
    {
        return $this->timeToRelease;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param QueueInterface $queue
     * @param LoggerInterface $logger
     * @param int $maxRetries
     * @return string
     */
    public function execute(QueueInterface $queue, LoggerInterface $logger, int $maxRetries=1): string
    {

        $job = clone $this->getJob();

        if (!$job instanceof JobInterface) {
            throw new \InvalidArgumentException('The provided job is not an instance of JobInterface.');
        }

        try {

            // Run set up
            $job->setUp($this);

            // Actual task execution
            $job->perform($this);

            // Run tear down
            $job->tearDown($this);

            // Update status to completed
            $this->setStatus('completed');

        } catch (\Exception | \Throwable  $e) {

            $retries = $this->getRetries();

            if ($retries < $maxRetries) {

                $this->setRetries($retries + 1);

                // Requeue the task
                try {
                    $queue->enqueue($this);
                } catch (\Exception | \Throwable  $ex) {
                    $logger->error($ex->getMessage());
                    $logger->error($ex->getTraceAsString());
                }

            } else {

                // Failed, we mark as failed
                $queue->fail($this);

                $logger->error('Failed with error', ['error' => $e->getMessage()]);

                $this->setStatus('failed');
            }
        }

        return $this->getStatus();

    }
}

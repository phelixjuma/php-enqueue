<?php
namespace Phelixjuma\Enqueue;

use Exception;
use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerInterface;

class Task
{


    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    private $job;
    private $args;
    protected $listenerDirectory;
    protected $listenerNamespace;
    private $id;
    private $status;
    private $retries = 0;
    private $created_at;

    private $delay;
    private $timeToRelease;
    private $priority;

    protected Schedule $schedule;

    /**
     * @param $job
     * @param $args
     * @param int $delay
     * @param int $timeToRelease
     * @param int $priority
     * @param $listenerDir
     * @param $listenerNamespace
     * @param $id
     * @throws Exception
     */
    public function __construct($job, $args = null, $delay=Pheanstalk::DEFAULT_DELAY, $timeToRelease=Pheanstalk::DEFAULT_TTR, $priority=Pheanstalk::DEFAULT_PRIORITY, $listenerDir = null, $listenerNamespace=null, $id = null)
    {
        $this->job = $job;
        $this->args = $args;
        $this->status = 'pending';
        $this->listenerDirectory = $listenerDir;
        $this->listenerNamespace = $listenerNamespace;
        $this->id = !empty($id) ? $id : self::generateRandomId();
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

    public function getId()
    {
        return $this->id;
    }

    public function getKey() {
        return "TASK_" . $this->getId();
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
     * @return Schedule|null
     */
    public function getSchedule(): ?Schedule
    {
        return !empty($this->schedule) ? $this->schedule : null;
    }

    /**
     * @return string
     * @throws Exception
     */
    private static function generateRandomId() {
        $token = '';
        $length = 10;
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        for ($i = 0; $i < $length; $i++) {
            $randomKey = random_int(0, strlen($alphabet)-1);
            $token .= $alphabet[$randomKey];
        }
        return $token;
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
            $this->setStatus(self::STATUS_COMPLETED);

        } catch (Exception | \Throwable  $e) {

            print "\nError in task execution: {$e->getMessage()}\n";

            $retries = $this->getRetries();

            if ($retries < $maxRetries) {

                $this->setRetries($retries + 1);

                // Requeue the task
                try {
                    $queue->enqueue($this);
                } catch (Exception | \Throwable  $ex) {
                    $logger->error($e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . " Trace: " . $e->getTraceAsString());
                }

            } else {

                // Failed, we mark as failed
                $queue->fail($this);

                $logger->error('Failed with error', ['error' => $e->getMessage()]);

                $this->setStatus(self::STATUS_FAILED);
            }
        }

        return $this->getStatus();

    }
}

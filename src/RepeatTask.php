<?php

namespace Phelixjuma\Enqueue;

use Exception;
use Psr\Log\LoggerInterface;

class RepeatTask extends Task {

    /**
     * @param $job
     * @param $args
     * @param Schedule $schedule
     * @param $id
     * @throws Exception
     */
    public function __construct($job, $args, Schedule $schedule, $id = null) {

        parent::__construct($job, $args, '', '', '', '', '', $id);

        $this->schedule = $schedule;

        $this->schedule->calculateNextRun();

    }

    /**
     * @param QueueInterface $queue
     * @param LoggerInterface $logger
     * @param int $maxRetries
     * @return string
     * @throws Exception
     */
    public function execute(QueueInterface $queue, LoggerInterface $logger, int $maxRetries=1): string {

        $result = parent::execute($queue, $logger, $maxRetries);

        if ($result === self::STATUS_COMPLETED) {

            $this->schedule->calculateNextRun();  // Update next run time after successful execution

            $queue->enqueue($this);  // Reschedule the task
        }
        return $result;
    }

}

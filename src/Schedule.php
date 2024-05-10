<?php

namespace Phelixjuma\Enqueue;

use Cron\CronExpression;
use Exception;

class Schedule  {

    private $specific_dates;
    private $expression;
    private $last_date;
    private $timezone;

    public $nextRun;

    /**
     * @param $specific_dates
     * @param $expression
     * @param $last_date
     * @throws Exception
     */
    public function __construct($specific_dates = null, $expression = null, $last_date= null)
    {
        $this->specific_dates = $specific_dates;
        $this->expression = $expression;
        $this->last_date = $last_date ? new \DateTime($last_date) : null;
        $this->timezone = "UTC";

        date_default_timezone_set($this->timezone);

        $this->calculateNextRun();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function calculateNextRun() {

        // Handle specific dates first
        $today = new \DateTime();
        $nextSpecificDate = $this->findNextSpecificDate($today);

        // Calculate next run based on the cron expression
        $nextCronRunDate = null;
        if (!empty($this->expression)) {
            $cron = new CronExpression($this->expression);
            $nextCronRunDate = $cron->getNextRunDate($today);
        }

        // Determine the earliest applicable next run date
        if ($nextSpecificDate && $nextCronRunDate) {
            $nextRunDate = min(new \DateTime($nextSpecificDate), $nextCronRunDate);
        } elseif ($nextSpecificDate) {
            $nextRunDate = new \DateTime($nextSpecificDate);
        } else {
            $nextRunDate = $nextCronRunDate;
        }

        // Consider the last date
        if ($this->last_date && $nextRunDate > $this->last_date) {
            $this->nextRun = null;  // Stop scheduling if next run exceeds the last date
        } else {
            $this->nextRun = $nextRunDate ? $nextRunDate->format('Y-m-d H:i:s') : null;
        }
    }

    /**
     * @param \DateTime $fromDate
     * @return mixed|null
     * @throws Exception
     */
    private function findNextSpecificDate(\DateTime $fromDate) {

        if (empty($this->specific_dates)) {
            return null;
        }

        $dates = array_filter($this->specific_dates, function($date) use ($fromDate) {
            return new \DateTime($date) >= $fromDate;
        });
        sort($dates);
        return $dates ? array_shift($dates) : null;
    }

    public function shouldRun(): bool {

        $today = new \DateTime();
        $todayStr = $today->format('Y-m-d');

        // Check if today matches any specific date or cron
        return in_array($todayStr, $this->specific_dates) || (new CronExpression($this->expression))->isDue();

    }

    /**
     * @return bool
     */
    public function isPastDue(): bool
    {
        return is_null($this->nextRun);
    }
}

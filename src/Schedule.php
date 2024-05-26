<?php

namespace Phelixjuma\Enqueue;

use Phelixjuma\Enqueue\QuartzCronExpression\CronExpression;
use Exception;

class Schedule  {

    private $specific_dates;
    private $expression;
    private $last_date;
    public $timezone;

    public $nextRun;

    /**
     * @var CronExpression $cron
     */
    protected CronExpression $cron;

    /**
     * @param $specific_dates
     * @param $expression
     * @param $last_date
     * @param $timezone
     * @throws Exception
     */
    public function __construct($specific_dates = null, $expression = null, $last_date= null, $timezone = 'UTC')
    {
        $this->specific_dates = $specific_dates;
        $this->expression = $expression;
        $this->timezone = new \DateTimeZone($timezone);
        $this->last_date = $last_date ? new \DateTime($last_date, $this->timezone) : null;

        $this->calculateNextRun();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function calculateNextRun() {

        // Handle specific dates first
        $today = new \DateTime('now', $this->timezone);
        $nextSpecificDate = $this->findNextSpecificDate($today);

        // Calculate next run based on the cron expression
        $nextCronRunDate = null;
        if (!empty($this->expression)) {
            $this->cron = new CronExpression($this->expression);
            $nextCronRunDate = $this->cron->getNextRunDate($today, 0, false, $this->timezone);
        }

        // Determine the earliest applicable next run date
        if ($nextSpecificDate && $nextCronRunDate) {
            $nextRunDate = min(new \DateTime($nextSpecificDate, $this->timezone), $nextCronRunDate);
        } elseif ($nextSpecificDate) {
            $nextRunDate = new \DateTime($nextSpecificDate, $this->timezone);
        } else {
            $nextRunDate = $nextCronRunDate;
        }

        // Consider the last date
        if ($this->last_date && $nextRunDate > $this->last_date) {
            $this->nextRun = null;  // Stop scheduling if next run exceeds the last date
        } else {
            $this->nextRun = $nextRunDate ? $nextRunDate->format('Y-m-d H:i:sP') : null;
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
            return new \DateTime($date, $this->timezone) >= $fromDate;
        });
        sort($dates);
        return $dates ? array_shift($dates) : null;
    }

    /**
     * @return bool
     */
    public function isPastDue(): bool
    {
        return is_null($this->nextRun);
    }

    /**
     * @param int $total
     * @param $currentTime
     * @param bool $invert
     * @param bool $allowCurrentDate
     * @return mixed|null
     * @throws Exception
     */
    public function getNextRunDates(int $total, $currentTime = 'now', bool $invert = false, bool $allowCurrentDate = false) {

        $today = new \DateTime('now', $this->timezone);

        if (!empty($this->expression)) {

            $runDates = $this->cron->getMultipleRunDates($total, $currentTime, $invert, $allowCurrentDate, $this->timezone);

            return array_filter($runDates, function($date) use($today) {
                return $date >= $today && $date <= $this->last_date;
            });

        } else {

            return array_filter($this->specific_dates, function($date) use ($today) {
                $dt = new \DateTime($date, $this->timezone);
                return $dt >= $today && $dt <= $this->last_date;
            });
        }
    }
}

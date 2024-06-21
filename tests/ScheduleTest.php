<?php
namespace Phelixjuma\Enqueue\Tests;


use Phelixjuma\Enqueue\QuartzCronExpression\CronExpression;
use Phelixjuma\Enqueue\Schedule;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function _testSchedule()
    {


//        $expression = '50 5 12 MAY ?'; // This will run every 10 seconds
//        $cron = new \Cron\CronExpression($expression);

        //$timezone = "UTC";
        $timezone = "Africa/Nairobi";
        $now = (new \DateTime("now", new \DateTimeZone($timezone)))->format("Y-m-d H:i:sP");
        $specific_dates = ['2024-05-26 16:20:00', '2024-05-26 16:25:00'];
        //$expression = '0 0 0 2L 1/1 ? *';
         $expression = '0 0 8 ? * MON*24/2,FRI*24/2 *';
        //$expression = '0 0 8 ? * MON *';
        $lastDate = "2024-05-26 16:22:00";

        $schedule = new Schedule(null, $expression, null, $timezone);

        print "\ncurrent time: $now\n";

        // Get the next run date
        $allRunDates = $schedule->getNextRunDates(5);
        print_r($allRunDates);

        $nextDate = $schedule->nextRun;

        print "\nNext run date: $nextDate\n";

    }
}

<?php
namespace Phelixjuma\Enqueue\Tests;


use Phelixjuma\Enqueue\QuartzCronExpression\CronExpression;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{
    public function testSchedule()
    {


//        $expression = '50 5 12 MAY ?'; // This will run every 10 seconds
//        $cron = new \Cron\CronExpression($expression);

        $now = date("Y-m-d H:i:s", time());
        print "\ncurrent time: $now\n";

        $expression = '9/8 0 0 ? * * *'; // This will run every 10 seconds
        $cron = new CronExpression($expression);

        // Get the next run date
        $allRunDates = $cron->getMultipleRunDates(5, 'now', false, true);
        print_r($allRunDates);
        $nextDate = $cron->getNextRunDate()->format("Y-m-d H:i:s");
        $prevDate = $cron->getPreviousRunDate()->format("Y-m-d H:i:s");
        print "\nNext run date: $nextDate\n";
        print "\nPrevious run date: $prevDate\n";

        // Check if the cron is due to run at the current date
        if ($cron->isDue()) {
            echo "Cron is due to run now!\n";
        } else {
            echo "Cron is not due now.\n";
        }
    }
}

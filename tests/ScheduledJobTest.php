<?php
namespace Phelixjuma\Enqueue\Tests;


use Exception;
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RepeatTask;
use Phelixjuma\Enqueue\Schedule;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;

class ScheduledJobTest extends TestCase
{
    /**
     * @return void
     * @throws Exception
     */
    public function _testSchedule()
    {

        $dates = ['2024-05-10', '2024-05-15'];

        $schedule = new Schedule(null, "* * * * *");

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '23456');

        print "\nscheduled task of id {$task->getId()}, key {$task->getKey()} . Status is {$task->getStatus()} and next run is {$task->schedule->nextRun}\n";
    }
}

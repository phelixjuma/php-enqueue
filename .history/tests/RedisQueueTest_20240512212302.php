<?php

namespace Phelixjuma\Enqueue\Tests;

use Phelixjuma\Enqueue\Event;
use Phelixjuma\Enqueue\Events\Events\EmailSentEvent;
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\RepeatTask;
use Phelixjuma\Enqueue\Schedule;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisQueueTest extends TestCase
{
    public function _testQueueingJob()
    {
        $redis = new Client('tcp://127.0.0.1:6379');
        $queue = new RedisQueue($redis);

        // Queue the task

        $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], ''));
    }

    public function _testQueueingEvent()
    {
        $redis = new Client('tcp://127.0.0.1:6379');
        $queue = new RedisQueue($redis);

        // Queue the task
        $listenerDir = '/usr/local/var/www/php-enqueue/src/Events/Listeners';
        $listenerNamespace = 'Phelixjuma\Enqueue\Events\Listeners';

        $queue
            ->setName('test_event_queue')
            ->enqueue(new Event(new EmailSentEvent(), ['email' => 'test@example.com'], '', '', '',  $listenerDir, $listenerNamespace, '23456'));

        // Fetch the task from the queue
//        $fetchedTask = $queue->fetch();
//
//        print_r($fetchedTask);
    }

    public function _testQueueingRepeatTask()
    {
        $redis = new Client('tcp://127.0.0.1:6379');
        $queue = new RedisQueue($redis);

        $cronExpression = "50 37 7 12 MAY-AUG ? 2023-2028";
        //$dates = ["2024-05-10 10:40:00", "2024-05-10 10:41:00", "2024-05-10 10:42:00"];
        $lastDate = "2024-05-10 11:55:00";
        $oneTimeDate = [$lastDate];

        $schedule = new Schedule('', $cronExpression, '');

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $now = date("Y-m-d H:i:s");
        print "\ntask key is {$task->getKey()}. Time is $now\n";

        $queue
            ->setName('periodic_reports')
            ->enqueue($task);
    }

    public function _testUpdateTask()
    {
        $redis = new Client('tcp://127.0.0.1:6379');
        $queue = new RedisQueue($redis);

        $cronExpression = "*/10 * * * *";
        $dates = ["2024-05-10 10:40:00", "2024-05-10 10:41:00", "2024-05-10 10:42:00"];
        $lastDate = "2024-05-10 11:15:00";

        $schedule = new Schedule('', $cronExpression, $lastDate);

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $now = time();
        print "\ntask key is {$task->getKey()}. Time is $now\n";

        $queue
            ->setName('periodic_reports')
            ->updateTask($task);
    }

    public function _testDeleteTask()
    {
        $redis = new Client('tcp://127.0.0.1:6379');
        $queue = new RedisQueue($redis);

        $cronExpression = "*/1 * * * *";
        //$dates = ["2024-05-10 10:40:00", "2024-05-10 10:41:00", "2024-05-10 10:42:00"];
        $lastDate = "2024-05-10 10:05:00";

        $schedule = new Schedule('', $cronExpression, "");

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $queue
            ->setName('periodic_reports')
            ->deleteTask($task);
    }
}

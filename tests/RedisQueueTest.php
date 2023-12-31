<?php

namespace Phelixjuma\Enqueue\Tests;

use Phelixjuma\Enqueue\Event;
use Phelixjuma\Enqueue\Events\Events\EmailSentEvent;
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
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
        $now = 0;
        $FiveSecs = 5;
        $TenSecs = 10;

        $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => $now], $now));

        $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => $FiveSecs], $FiveSecs));

        $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => $TenSecs], $TenSecs));

        // Fetch the task from the queue
        //$fetchedTask = $queue->fetch();

        //print_r($fetchedTask);
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
            ->enqueue(new Event(new EmailSentEvent(), ['email' => 'test@example.com'], $listenerDir, $listenerNamespace));

        // Fetch the task from the queue
//        $fetchedTask = $queue->fetch();
//
//        print_r($fetchedTask);
    }
}

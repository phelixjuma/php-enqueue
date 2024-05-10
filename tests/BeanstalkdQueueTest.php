<?php

namespace Phelixjuma\Enqueue\Tests;

use Pheanstalk\Pheanstalk;
use Phelixjuma\Enqueue\BeanstalkdQueue;
use Phelixjuma\Enqueue\Event;
use Phelixjuma\Enqueue\Events\Events\EmailSentEvent;
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class BeanstalkdQueueTest extends TestCase
{
    public function _testQueueingJob()
    {
        $pheanstalk = Pheanstalk::create('127.0.0.1', 11300);
        $queue = new BeanstalkdQueue($pheanstalk);

        // Queue the task
        $now = 0;
        $FiveSecs = 10;
        $TenSecs = 20;

        $queue
            ->setName('staging.test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => $now], $now));

        $queue
            ->setName('staging.test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => $FiveSecs], $FiveSecs));

        $queue
            ->setName('staging.test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => $TenSecs], $TenSecs));
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

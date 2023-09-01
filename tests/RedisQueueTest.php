<?php

namespace Phelixjuma\Enqueue\Tests;

use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisQueueTest extends TestCase
{
    public function testQueueing()
    {
        $redis = new Client('tcp://127.0.0.1:6379');
        $queue = new RedisQueue($redis);

        // Queue the task
        $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['some_arg' => 'some_value']));

        // Fetch the task from the queue
        $fetchedTask = $queue->fetch();
    }
}

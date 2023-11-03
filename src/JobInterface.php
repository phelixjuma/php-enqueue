<?php

namespace Phelixjuma\Enqueue;

use Pheanstalk\Values\Job;

interface JobInterface
{
    public function setUp(Task $task);
    public function perform(Task $task, QueueInterface $queue=null, Job $job=null);
    public function tearDown(Task $task);
}

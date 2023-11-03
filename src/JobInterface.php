<?php

namespace Phelixjuma\Enqueue;

use Pheanstalk\Values\Job;

interface JobInterface
{
    public function setUp(Task $task);
    public function perform(Task $task);
    public function tearDown(Task $task);
}

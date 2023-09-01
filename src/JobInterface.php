<?php

namespace Phelixjuma\Enqueue;

interface JobInterface
{
    public function setUp(Task $task);
    public function perform(Task $task);
    public function tearDown(Task $task);
}

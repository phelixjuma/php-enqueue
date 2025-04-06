<?php

namespace Phelixjuma\Enqueue;

use Predis\Client;
use Pheanstalk\Pheanstalk;
use Phelixjuma\Enqueue\Task;

interface QueueInterface
{ 

    /**
     * @return mixed
     */
    public function setName($name);
    public function getName();
    /**
     * @param Task $task
     * @return void
     */
    public function enqueue(Task $task): bool;
    public function fail(Task $task): bool;
    public function getClient(): Client|Pheanstalk;

    public function fetch(): ?Task;
    public function fetchScheduled(): ?Task;

}

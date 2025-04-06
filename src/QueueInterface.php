<?php

namespace Phelixjuma\Enqueue;

use Predis\Client;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;
use Phelixjuma\Enqueue\Task;

interface QueueInterface
{ 

    /**
     * @return mixed
     */
    public function setName($name);
    public function getName();
    public function getQueue(): TubeName|string;
    public function enqueue(Task $task): bool;
    public function fail(Task $task): bool;
    public function getClient(): Client|Pheanstalk;

    public function fetch(): ?Task;
    public function fetchScheduled(): ?Task;

}

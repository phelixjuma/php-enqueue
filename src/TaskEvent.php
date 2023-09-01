<?php
namespace Phelixjuma\Enqueue;

use Symfony\Contracts\EventDispatcher\Event;

class TaskEvent extends Event
{
    private $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function getTask()
    {
        return $this->task;
    }
}

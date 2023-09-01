<?php
namespace Phelixjuma\Enqueue;

use Symfony\Contracts\EventDispatcher\Event;

class WorkerEvent extends Event
{
    private $worker;

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }

    public function getWorker()
    {
        return $this->worker;
    }
}

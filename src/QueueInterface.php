<?php

namespace Phelixjuma\Enqueue;


interface QueueInterface
{

    /**
     * @return mixed
     */
    public function setName($name);
    /**
     * @param Task $task
     * @return void
     */
    public function enqueue(Task $task);
    public function fail(Task $task);

}

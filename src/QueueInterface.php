<?php

namespace Phelixjuma\Enqueue;


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

}

<?php
namespace Phelixjuma\Enqueue;

use Symfony\Component\Console\Application;
use Phelixjuma\Enqueue\Commands\AddCommand;
use Phelixjuma\Enqueue\Commands\ListCommand;
use Phelixjuma\Enqueue\Commands\RemoveCommand;

class Manager extends Application
{
    private $queue;

    public function __construct(RedisQueue $queue)
    {
        parent::__construct('Phelix juma Enqueue Manager', '1.0.0');

        $this->queue = $queue;

        $this->addCommands([
            new AddCommand($this->queue),
            new ListCommand($this->queue),
            new RemoveCommand($this->queue),
        ]);
    }
}

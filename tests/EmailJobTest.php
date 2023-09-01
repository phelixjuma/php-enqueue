<?php
namespace Phelixjuma\Enqueue\Tests;


use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;

class EmailJobTest extends TestCase
{
    public function _testPerform()
    {
        $task = new Task(new EmailJob(), ['email' => 'test@example.com']);

        print "scheduled task ".$task->getStatus();
    }
}

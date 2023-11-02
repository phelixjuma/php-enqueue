<?php
namespace Phelixjuma\Enqueue\Tests;


use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;

class EmailJobTest extends TestCase
{
    public function _testPerform()
    {

        $executeAt = (new \DateTime())->add(new \DateInterval('PT30S'))->format("Y-m-d H:i:s");

        $task = new Task(new EmailJob(), ['email' => 'test@example.com'], $executeAt);

        print "scheduled task ".$task->getStatus();
    }
}

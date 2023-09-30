<?php
namespace Phelixjuma\Enqueue\Tests;


use Phelixjuma\Enqueue\Event;
use Phelixjuma\Enqueue\Events\Events\EmailSentEvent;
use PHPUnit\Framework\TestCase;

class ListenerTest extends TestCase
{
    public function testPerform()
    {

        $event = new Event(new EmailSentEvent(), ['email' => 'test@example.com']);

        print "triggered event ".$event->getStatus();
    }
}

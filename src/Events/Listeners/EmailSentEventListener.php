<?php

namespace Phelixjuma\Enqueue\Events\Listeners;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phelixjuma\Enqueue\Event;
use Phelixjuma\Enqueue\ListenerInterface;
use Phelixjuma\Enqueue\Listener;

class EmailSentEventListener implements ListenerInterface
{

    /**
     * @var Logger
     */
    private $logger;

    public function setUp(Event $event)
    {
        $this->logger = new Logger("email_listener_job");
        $this->logger->pushHandler(new StreamHandler('/usr/local/var/www/php-enqueue/log/email-sent-listener.log', Logger::DEBUG));
    }

    public function tearDown(Event $event)
    {
    }

    /**
     * @Listener(for="EmailSentEvent")
     */
    public function logResponse(Event $event) {
        $this->logger->info("EmailSentListener::logResponse. Args: ".json_encode($event->getArgs()));
    }

    /**
     * @Listener(for="Phelixjuma\Enqueue\Events\Events\EmailSentEvent")
     */
    public function askForSomething(Event $event) {
        $this->logger->info("EmailSentListener::askForSomething. Args: ".json_encode($event->getArgs()));
    }
}


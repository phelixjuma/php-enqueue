<?php

namespace Phelixjuma\Enqueue;

use Psr\Log\LoggerInterface;

class Event extends Task {

    /**
     * @param RedisQueue $queue
     * @param LoggerInterface $logger
     * @param int $maxRetries
     * @return string
     */
    public function execute(QueueInterface $queue, LoggerInterface $logger, int $maxRetries=1): string {

        $event = $this->getJob();

        if (!$event instanceof EventInterface) {
            throw new \InvalidArgumentException('The provided job is not an instance of EventInterface.');
        }

        try {
            // Fetch listeners using annotations
            $listeners = (new EventDiscovery())
               ->registerDirectory($this->listenerDirectory, $this->listenerNamespace)
               ->getListenersForEvent(get_class($event));

            foreach ($listeners as [$listenerClass, $method]) {

                $listenerInstance = new $listenerClass();

                if (!$listenerInstance instanceof ListenerInterface) {
                    throw new \InvalidArgumentException('The provided job is not an instance of EventInterface.');
                }

                // Run set up for the listener
                $listenerInstance->setUp($this);

                // Handle the event
                $listenerInstance->$method($this);

                // Run tear down for the listener
                $listenerInstance->tearDown($this);
            }

            // Update status to completed
            $this->setStatus('completed');

        } catch (\Exception | \Throwable $e) {
            $retries = $this->getRetries();

            if ($retries < $maxRetries) {
                $this->setRetries($retries + 1);
                $queue->enqueue($this);
            } else {
                $queue->fail($this);
                $logger->error('Failed with error', ['error' => $e->getMessage()]);
                $this->setStatus('failed');
            }
        }

        return $this->getStatus();
    }
}

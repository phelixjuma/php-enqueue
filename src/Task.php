<?php
namespace Phelixjuma\Enqueue;

class Task
{
    private $job;
    private $args;
    private $status;
    private $retries = 0;
    private $created_at;

    public function __construct($job, $args = null)
    {
        $this->job = $job;
        $this->args = $args;
        $this->status = 'pending';
        $this->created_at = new \DateTime();
    }

    public function getJob()
    {
        return $this->job;
    }

    public function getArgs()
    {
        return $this->args;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getRetries()
    {
        return $this->retries;
    }

    public function setRetries($retries)
    {
        $this->retries = $retries;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }
}

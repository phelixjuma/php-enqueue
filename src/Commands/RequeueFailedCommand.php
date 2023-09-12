<?php
namespace Phelixjuma\Enqueue\Commands;

use Phelixjuma\Enqueue\RedisQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RequeueFailedCommand extends Command
{
    protected static $defaultName = 'enqueue:failed:requeue';
    private $queue;

    public function __construct(RedisQueue $queue)
    {
        parent::__construct();
        $this->queue = $queue;
    }

    protected function configure()
    {
        $this
            ->setName('enqueue:failed:requeue')
            ->setDescription('Re-enqueue all failed jobs in a queue.')
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of the queue',
                'default'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queueName = $input->getOption('queue');

        $this->queue->setName($queueName)->requeueFailedJobs();

        $output->writeln("<info>Requeued all failed tasks</info>");
        return 0;
    }
}

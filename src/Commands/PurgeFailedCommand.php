<?php
namespace Phelixjuma\Enqueue\Commands;

use Phelixjuma\Enqueue\RedisQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeFailedCommand extends Command
{
    protected static $defaultName = 'enqueue:failed:purge';
    private $queue;

    public function __construct(RedisQueue $queue)
    {
        parent::__construct();
        $this->queue = $queue;
    }

    protected function configure()
    {
        $this
            ->setName('enqueue:failed:purge')
            ->setDescription('Remove all failed jobs')
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of the queue',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queueName = $input->getOption('queue');

        $this->queue->setName($queueName)->removeAllFailedJobs();

        $output->writeln("<info>Removed all failed jobs</info>");
        return 0;
    }
}

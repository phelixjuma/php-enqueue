<?php
namespace Phelixjuma\Enqueue\Commands;

use Phelixjuma\Enqueue\RedisQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends Command
{
    protected static $defaultName = 'enqueue:remove';
    private $queue;

    public function __construct(RedisQueue $queue)
    {
        parent::__construct();
        $this->queue = $queue;
    }

    protected function configure()
    {
        $this
            ->setName('enqueue:remove')
            ->setDescription('Remove a task from the queue.')
            ->addOption(
                'project_root',
                null,
                InputOption::VALUE_OPTIONAL,
                'The path to the project root',
                'default'
            )
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of the queue',
                'default'
            )
            ->addOption(
                'taskId',
                null,
                InputOption::VALUE_REQUIRED,
                'The ID of the task to remove',
                ''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queueName = $input->getOption('queue');
        $taskId = $input->getOption('taskId');

        $this->queue->setName($queueName)->remove($taskId);

        $output->writeln("<info>Task with ID $taskId removed from the queue</info>");
        return 0;
    }
}

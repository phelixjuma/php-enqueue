<?php
namespace Phelixjuma\Enqueue\Commands;

use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\Task;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddCommand extends Command
{
    protected static $defaultName = 'enqueue:add';
    private $queue;

    public function __construct(RedisQueue $queue)
    {
        parent::__construct();
        $this->queue = $queue;
    }

    protected function configure()
    {
        $this
            ->setName('enqueue:add')
            ->setDescription('Add a task to the queue.')
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of the queue',
                null
            )
            // configure the class option
            ->addOption(
                'class',
                null,
                InputOption::VALUE_REQUIRED,
                'The class of the job to add',
                null
            )
            ->addOption(
                'parameters',
                null,
                InputOption::VALUE_REQUIRED,
                'The parameters of the job to add',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queueName = $input->getOption('queue');
        $class = $input->getOption('class');
        $parameters = $input->getOption('parameters');

        $task = new Task($class, $parameters);

        $this->queue->setName($queueName)->enqueue($task);

        $output->writeln('<info>Task added to the queue</info>');
        return 0;
    }
}

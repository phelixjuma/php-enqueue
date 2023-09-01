<?php
namespace Phelixjuma\Enqueue\Commands;

use Phelixjuma\Enqueue\RedisQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Predis\Client;

class ListCommand extends Command
{
    protected static $defaultName = 'enqueue:list';
    private $queue;

    public function __construct(RedisQueue $queue)
    {
        parent::__construct();
        $this->queue = $queue;
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('enqueue:list')

            // the short description shown while running "php bin/console list"
            ->setDescription('Adds a task to the queue.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to add tasks to the queue...')
            // configure the queue option
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

        // Fetch jobs from Redis queue
        $tasks = $this->queue->setName($queueName)->list();

        $table = new Table($output);
        $table->setHeaders(['Job', 'Args', 'Status', 'Created At', 'Retries']);

        foreach ($tasks as $task) {
            $table->addRow([
                get_class($task->getJob()),
                json_encode($task->getArgs()),
                $task->getStatus(),
                $task->getCreatedAt()->format('Y-m-d H:i:s'),
                $task->getRetries(),
            ]);
        }

        $table->render();

        return 0;
    }
}

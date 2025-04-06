<?php

namespace Phelixjuma\Enqueue\Tests;

use Phelixjuma\Enqueue\Event;
use Phelixjuma\Enqueue\Events\Events\EmailSentEvent;
use Phelixjuma\Enqueue\Jobs\EmailJob;
use Phelixjuma\Enqueue\RedisQueue;
use Phelixjuma\Enqueue\RepeatTask;
use Phelixjuma\Enqueue\Schedule;
use Phelixjuma\Enqueue\Task;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisQueueTest extends TestCase
{
    protected Client $localRedis;
    protected Client $valKeyRedis;
    protected array $localConfig;
    protected array $valKeyConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Load environment variables from .env file
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        try {
            $dotenv->load();
            
            // Required environment variables for ValKey tests
            $dotenv->required([
                'AWS_VALKEY_ENDPOINT',
                'AWS_VALKEY_USERNAME',
                'AWS_VALKEY_PASSWORD'
            ])->notEmpty();
            
            // Optional environment variables with defaults
            $valKeyPort = getenv('AWS_VALKEY_PORT') ?: 6379;
            $localHost = getenv('LOCAL_REDIS_HOST') ?: '127.0.0.1';
            $localPort = getenv('LOCAL_REDIS_PORT') ?: 6379;
            $localPassword = getenv('LOCAL_REDIS_PASSWORD') ?: null;

            // Set up ValKey configuration
            $this->valKeyConfig = [
                'parameters' => [
                    'scheme' => 'tls',
                    'host' => getenv('AWS_VALKEY_ENDPOINT'),
                    'port' => $valKeyPort,
                    'username' => getenv('AWS_VALKEY_USERNAME'),
                    'password' => getenv('AWS_VALKEY_PASSWORD')
                ],
                'options' => [
                    'parameters' => [
                        'username' => getenv('AWS_VALKEY_USERNAME'),
                        'password' => getenv('AWS_VALKEY_PASSWORD')
                    ],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true
                    ]
                ]
            ];

            // Set up local Redis configuration
            $this->localConfig = [
                'parameters' => [
                    'scheme' => 'tcp',
                    'host' => $localHost,
                    'port' => $localPort
                ],
                'options' => []
            ];

            if ($localPassword) {
                $this->localConfig['parameters']['password'] = $localPassword;
                $this->localConfig['options']['parameters']['password'] = $localPassword;
            }

            // Initialize Redis clients
            $this->localRedis = new Client(
                $this->localConfig['parameters'],
                $this->localConfig['options']
            );

            $this->valKeyRedis = new Client(
                $this->valKeyConfig['parameters'],
                $this->valKeyConfig['options']
            );
            
        } catch (\Dotenv\Exception\ValidationException $e) {
            $this->markTestSkipped('Missing required environment variables: ' . $e->getMessage());
        } catch (\Dotenv\Exception\InvalidPathException $e) {
            $this->markTestSkipped('.env file not found');
        }
    }

    public function testLocalRedisConnection()
    {
        $queue = new RedisQueue($this->localRedis);

        // Queue a test task
        $result = $queue
            ->setName('local_test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], 'test-task'));

        $this->assertTrue($result);

        // Fetch and verify the task
        $fetchedTask = $queue->fetch();
        $this->assertInstanceOf(Task::class, $fetchedTask);
    }

    public function testAwsValKeyConnection()
    {
        try {
            // Verify connection by trying a simple command
            $pong = $this->valKeyRedis->ping();
            $this->assertEquals('PONG', $pong, 'Redis connection failed');
            
            $queue = new RedisQueue($this->valKeyRedis);

            // Queue a test task
            $result = $queue
                ->setName('valkey_test_queue')
                ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], 'valkey-test-task'));

            $this->assertTrue($result);

            // Fetch and verify the task
            $fetchedTask = $queue->fetch();
            $this->assertInstanceOf(Task::class, $fetchedTask);
            
        } catch (\Predis\Connection\ConnectionException $e) {
            $this->fail('Failed to connect to ValKey: ' . $e->getMessage());
        } catch (\Predis\Response\ServerException $e) {
            $this->fail('Redis server error: ' . $e->getMessage());
        }
    }

    public function testQueueingJob()
    {
        $queue = new RedisQueue($this->valKeyRedis);
        
        $result = $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], ''));
            
        $this->assertTrue($result);
    }

    public function testQueueingEvent()
    {
        $queue = new RedisQueue($this->valKeyRedis);

        $listenerDir = '/usr/local/var/www/php-enqueue/src/Events/Listeners';
        $listenerNamespace = 'Phelixjuma\Enqueue\Events\Listeners';

        $result = $queue
            ->setName('test_event_queue')
            ->enqueue(new Event(new EmailSentEvent(), ['email' => 'test@example.com'], '', '', '', $listenerDir, $listenerNamespace, '23456'));
            
        $this->assertTrue($result);
    }

    public function testQueueingRepeatTask()
    {
        $queue = new RedisQueue($this->valKeyRedis);

        $cronExpression = "50 37 7 12 MAY-AUG ? 2023-2028";
        $lastDate = "2024-05-10 11:55:00";
        $schedule = new Schedule('', $cronExpression, '');

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $result = $queue
            ->setName('periodic_reports')
            ->enqueue($task);
            
        $this->assertTrue($result);
    }

    public function testUpdateTask()
    {
        $queue = new RedisQueue($this->valKeyRedis);

        $cronExpression = "*/10 * * * *";
        $lastDate = "2024-05-10 11:15:00";
        $schedule = new Schedule('', $cronExpression, $lastDate);

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $result = $queue
            ->setName('periodic_reports')
            ->updateTask($task);
            
        $this->assertTrue($result);
    }

    public function testDeleteTask()
    {
        $queue = new RedisQueue($this->valKeyRedis);

        $cronExpression = "*/1 * * * *";
        $schedule = new Schedule('', $cronExpression, "");

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $result = $queue
            ->setName('periodic_reports')
            ->deleteTask($task);
            
        $this->assertTrue($result);
    }
}

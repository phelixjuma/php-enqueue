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
    protected function setUp(): void
    {
        parent::setUp();

        try {
            // Get the absolute path to the project root
            $projectRoot = dirname(__DIR__);
            print("\nProject Root: " . $projectRoot);
            
            // Check if .env file exists
            if (!file_exists($projectRoot . '/.env')) {
                print("\n.env file not found in {$projectRoot}/.env");
                print("\nTrying to copy .env.example to .env...");
                
                if (file_exists($projectRoot . '/.env.example')) {
                    copy($projectRoot . '/.env.example', $projectRoot . '/.env');
                    print("\nCopied .env.example to .env");
                } else {
                    throw new \Exception(".env.example file not found in {$projectRoot}");
                }
            }

            // Load environment variables from .env file
            $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->load();
            
            // Debug: Print all relevant environment variables
            print("\n\nEnvironment File Contents:");
            print("\n" . file_get_contents($projectRoot . '/.env'));
            
            print("\n\nEnvironment Variables Loaded:");
            print("\nAWS_VALKEY_ENDPOINT: " . $_ENV['AWS_VALKEY_ENDPOINT'] ?? 'not set in $_ENV');
            print("\nAWS_VALKEY_USERNAME: " . $_ENV['AWS_VALKEY_USERNAME'] ?? 'not set in $_ENV');
            print("\nAWS_VALKEY_PORT: " . $_ENV['AWS_VALKEY_PORT'] ?? 'not set in $_ENV');
            
            print("\n\nGetenv Results:");
            print("\nAWS_VALKEY_ENDPOINT: " . getenv('AWS_VALKEY_ENDPOINT') ?: 'not set via getenv');
            print("\nAWS_VALKEY_USERNAME: " . getenv('AWS_VALKEY_USERNAME') ?: 'not set via getenv');
            print("\nAWS_VALKEY_PORT: " . getenv('AWS_VALKEY_PORT') ?: 'not set via getenv');
            
            // Required environment variables for ValKey tests
            $dotenv->required([
                'AWS_VALKEY_ENDPOINT',
                'AWS_VALKEY_USERNAME',
                'AWS_VALKEY_PASSWORD'
            ])->notEmpty();
            
            // Store the values in class properties for use in tests
            $this->valKeyEndpoint = $_ENV['AWS_VALKEY_ENDPOINT'] ?? null;
            $this->valKeyUsername = $_ENV['AWS_VALKEY_USERNAME'] ?? null;
            $this->valKeyPassword = $_ENV['AWS_VALKEY_PASSWORD'] ?? null;
            $this->valKeyPort = $_ENV['AWS_VALKEY_PORT'] ?? 6379;
            
            if (!$this->valKeyEndpoint || !$this->valKeyUsername || !$this->valKeyPassword) {
                throw new \Exception("Required ValKey configuration is missing after loading .env file");
            }
            
        } catch (\Dotenv\Exception\ValidationException $e) {
            print("\nValidation Error: " . $e->getMessage());
            $this->markTestSkipped('Environment validation failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            print("\nError: " . $e->getMessage());
            $this->markTestSkipped('Environment setup failed: ' . $e->getMessage());
        }
    }

    public function _testQueueingJob()
    {
        $parameters = [
            'scheme' => 'tls',  // For SSL/TLS connection
            'host' => 'tcp://127.0.0.1:6379',
            'port' => 6379,
            'password' => 'your-auth-token'  // If using AUTH token
        ];

        $options = [
            'parameters' => [
                'password' => 'your-auth-token'
            ],
            'ssl' => [
                'verify_peer' => false,  // You might want to set this to true in production
                'verify_peer_name' => false  // You might want to set this to true in production
            ]
        ];

        $redis = new Client($parameters, $options);
        $queue = new RedisQueue($redis);

        // Queue the task 

        $queue
            ->setName('test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], ''));
    }

    public function _testQueueingEvent()
    {
        $parameters = [
            'scheme' => 'tls',  // For SSL/TLS connection
            'host' => 'your-elasticache-endpoint.region.cache.amazonaws.com',
            'port' => 6379,
            'password' => 'your-auth-token'  // If using AUTH token
        ];

        $options = [
            'parameters' => [
                'password' => 'your-auth-token'
            ],
            'ssl' => [
                'verify_peer' => false,  // You might want to set this to true in production
                'verify_peer_name' => false  // You might want to set this to true in production
            ]
        ];

        $redis = new Client($parameters, $options);
        $queue = new RedisQueue($redis);

        // Queue the task
        $listenerDir = '/usr/local/var/www/php-enqueue/src/Events/Listeners';
        $listenerNamespace = 'Phelixjuma\Enqueue\Events\Listeners';

        $queue
            ->setName('test_event_queue')
            ->enqueue(new Event(new EmailSentEvent(), ['email' => 'test@example.com'], '', '', '',  $listenerDir, $listenerNamespace, '23456'));

        // Fetch the task from the queue
//        $fetchedTask = $queue->fetch();
//
//        print_r($fetchedTask);
    }

    public function _testQueueingRepeatTask()
    {
        $parameters = [
            'scheme' => 'tls',  // For SSL/TLS connection
            'host' => 'your-elasticache-endpoint.region.cache.amazonaws.com',
            'port' => 6379,
            'password' => 'your-auth-token'  // If using AUTH token
        ];

        $options = [
            'parameters' => [
                'password' => 'your-auth-token'
            ],
            'ssl' => [
                'verify_peer' => false,  // You might want to set this to true in production
                'verify_peer_name' => false  // You might want to set this to true in production
            ]
        ];

        $redis = new Client($parameters, $options);
        $queue = new RedisQueue($redis);

        $cronExpression = "50 37 7 12 MAY-AUG ? 2023-2028";
        //$dates = ["2024-05-10 10:40:00", "2024-05-10 10:41:00", "2024-05-10 10:42:00"];
        $lastDate = "2024-05-10 11:55:00";
        $oneTimeDate = [$lastDate];

        $schedule = new Schedule('', $cronExpression, '');

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $now = date("Y-m-d H:i:s");
        print "\ntask key is {$task->getKey()}. Time is $now\n";

        $queue
            ->setName('periodic_reports')
            ->enqueue($task);
    }

    public function _testUpdateTask()
    {
        $parameters = [
            'scheme' => 'tls',  // For SSL/TLS connection
            'host' => 'your-elasticache-endpoint.region.cache.amazonaws.com',
            'port' => 6379,
            'password' => 'your-auth-token'  // If using AUTH token
        ];

        $options = [
            'parameters' => [
                'password' => 'your-auth-token'
            ],
            'ssl' => [
                'verify_peer' => false,  // You might want to set this to true in production
                'verify_peer_name' => false  // You might want to set this to true in production
            ]
        ];

        $redis = new Client($parameters, $options);
        $queue = new RedisQueue($redis);

        $cronExpression = "*/10 * * * *";
        $dates = ["2024-05-10 10:40:00", "2024-05-10 10:41:00", "2024-05-10 10:42:00"];
        $lastDate = "2024-05-10 11:15:00";

        $schedule = new Schedule('', $cronExpression, $lastDate);

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $now = time();
        print "\ntask key is {$task->getKey()}. Time is $now\n";

        $queue
            ->setName('periodic_reports')
            ->updateTask($task);
    }

    public function _testDeleteTask()
    {
        $parameters = [
            'scheme' => 'tls',  // For SSL/TLS connection
            'host' => 'your-elasticache-endpoint.region.cache.amazonaws.com',
            'port' => 6379,
            'password' => 'your-auth-token'  // If using AUTH token
        ];

        $options = [
            'parameters' => [
                'password' => 'your-auth-token'
            ],
            'ssl' => [
                'verify_peer' => false,  // You might want to set this to true in production
                'verify_peer_name' => false  // You might want to set this to true in production
            ]
        ];

        $redis = new Client($parameters, $options);
        $queue = new RedisQueue($redis);

        $cronExpression = "*/1 * * * *";
        //$dates = ["2024-05-10 10:40:00", "2024-05-10 10:41:00", "2024-05-10 10:42:00"];
        $lastDate = "2024-05-10 10:05:00";

        $schedule = new Schedule('', $cronExpression, "");

        $task = new RepeatTask(new EmailJob(), ['email' => 'test@example.com'], $schedule, '2345205');

        $queue
            ->setName('periodic_reports')
            ->deleteTask($task);
    }

    public function _testLocalRedisConnection()
    {
        // Local Redis connection
        $redis = new Client([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379
        ]);
        
        $queue = new RedisQueue($redis);

        // Queue a test task
        $result = $queue
            ->setName('local_test_queue')
            ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], 'test-task'));

        $this->assertTrue($result);

        // Fetch and verify the task
        $fetchedTask = $queue->fetch();

        print_r($fetchedTask);

        $this->assertInstanceOf(Task::class, $fetchedTask);
    }

    protected $valKeyEndpoint;
    protected $valKeyUsername;
    protected $valKeyPassword;
    protected $valKeyPort;

    public function testAwsValKeyConnection()
    {
        // AWS ValKey/ElastiCache connection configuration with RBAC
        $parameters = [
            'scheme' => 'tls',
            'host' => $this->valKeyEndpoint,
            'port' => $this->valKeyPort,
            // For RBAC, username is required along with password
            'username' => $this->valKeyUsername,
            'password' => $this->valKeyPassword,
            // Add timeout settings
            'read_write_timeout' => 60,
            'timeout' => 5
        ];

        $options = [
            'parameters' => [
                'username' => $this->valKeyUsername,
                'password' => $this->valKeyPassword,
                'timeout' => 5.0,
                'read_write_timeout' => 60.0
            ],
            'ssl' => [
                'verify_peer' => false,  // Temporarily disable SSL verification for testing
                'verify_peer_name' => false,
                'verify' => false
            ],
            'replication' => false
        ];

        print("\n\nTrying to connect with:");
        print("\nEndpoint: " . $this->valKeyEndpoint);
        print("\nUsername: " . $this->valKeyUsername);
        print("\nPort: " . $this->valKeyPort);
        print("\nTimeout settings: " . $parameters['timeout'] . "s connect, " . $parameters['read_write_timeout'] . "s read/write");

        try {
            $redis = new Client($parameters, $options);
            
            // Set connection timeout
            $redis->connect()->setTimeout(5.0);
            
            // Verify connection by trying a simple command
            print("\nTrying PING command...");
            $pong = $redis->ping();
            $this->assertEquals('PONG', $pong, 'Redis connection failed');
            print("\nPING successful!");
            
            $queue = new RedisQueue($redis);

            // Queue a test task
            print("\nTrying to enqueue task...");
            $result = $queue
                ->setName('valkey_test_queue')
                ->enqueue(new Task(new EmailJob(), ['time' => date("Y-m-d H:i:s", time())], 'valkey-test-task'));

            $this->assertTrue($result);
            print("\nTask enqueued successfully!");

            // Fetch and verify the task
            print("\nTrying to fetch task...");
            $fetchedTask = $queue->fetch();
            $this->assertInstanceOf(Task::class, $fetchedTask);
            print("\nTask fetched successfully!");
            
        } catch (\Predis\Connection\ConnectionException $e) {
            print("\nConnection Error Details:");
            print("\nMessage: " . $e->getMessage());
            print("\nCode: " . $e->getCode());
            if ($e->getPrevious()) {
                print("\nPrevious Error: " . $e->getPrevious()->getMessage());
            }
            $this->fail('Failed to connect to ValKey: ' . $e->getMessage());
        } catch (\Predis\Response\ServerException $e) {
            print("\nServer Error Details:");
            print("\nMessage: " . $e->getMessage());
            print("\nCode: " . $e->getCode());
            $this->fail('Redis server error: ' . $e->getMessage());
        } catch (\Exception $e) {
            print("\nUnexpected Error:");
            print("\nType: " . get_class($e));
            print("\nMessage: " . $e->getMessage());
            print("\nCode: " . $e->getCode());
            $this->fail('Unexpected error: ' . $e->getMessage());
        }
    }
}

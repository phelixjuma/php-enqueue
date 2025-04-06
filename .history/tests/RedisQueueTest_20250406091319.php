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

    public function testAwsValKeyConnection()
    {
        // AWS ValKey/ElastiCache connection configuration with RBAC
        $parameters = [
            'scheme' => 'tls',
            'host' => getenv('AWS_VALKEY_ENDPOINT') ?: 'your-elasticache-endpoint.cache.amazonaws.com',
            'port' => getenv('AWS_VALKEY_PORT') ?: 6379,
            // For RBAC, username is required along with password
            'username' => getenv('AWS_VALKEY_USERNAME'),
            'password' => getenv('AWS_VALKEY_AUTH_TOKEN')
        ];

        $options = [
            'parameters' => [
                'username' => getenv('AWS_VALKEY_USERNAME'),
                'password' => getenv('AWS_VALKEY_AUTH_TOKEN')
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        try {
            $redis = new Client($parameters, $options);
            
            // Verify RBAC authentication using ACL WHOAMI
            $authInfo = $redis->acl('WHOAMI');
            $this->assertNotEmpty($authInfo, 'RBAC authentication failed');
            
            $queue = new RedisQueue($redis);

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
}

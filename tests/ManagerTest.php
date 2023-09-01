<?php

namespace Phelixjuma\Enqueue\Tests;

use PHPUnit\Framework\TestCase;
use Phelixjuma\Enqueue\Manager;

class ManagerTest extends TestCase
{
    public function testConstructor()
    {
        $manager = new Manager();
        $this->assertInstanceOf(Manager::class, $manager);
    }
}

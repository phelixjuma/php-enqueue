<?php

namespace Phelixjuma\Enqueue;

interface ListenerInterface {

    public function setUp(Event $event);
    public function tearDown(Event $event);
}


<?php

namespace Service\Test;

use \Event\Test\TestEvent;

/**
 * 测试服务
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/5/22
 * Time: 14:24
 */
class TestService extends \Service\AbstractService
{
    /**
     *
     */
    public function do1()
    {
        echo 'service-do1-';
        $event = new TestEvent();
        \Listener\ListenerTrigger::trigger(TestEvent::class, $event::DO1, $event);
    }

    /**
     *
     */
    public function do2()
    {
        echo 'service-do2-';
        $event = new TestEvent();
        \Listener\ListenerTrigger::trigger(TestEvent::class, $event::DO2, $event);
    }
}
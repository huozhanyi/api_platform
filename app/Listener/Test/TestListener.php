<?php

namespace Listener\Test;

use Zend\EventManager\EventManagerInterface;
use Event\Test\TestEvent;

/**
 * 测试监听器
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/5/21
 * Time: 15:32
 */
class TestListener extends \Listener\AbstractListener
{
    /**
     * @param EventManagerInterface $events
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->getSharedManager()->attach(TestEvent::class, TestEvent::DO1, array($this, 'todo1'), 0);
        $this->listeners[] = $events->getSharedManager()->attach(TestEvent::class, TestEvent::DO2, array($this, 'todo2'), 0);
    }

    /**
     * @param TestEvent $e
     */
    public function todo1(TestEvent $e)
    {
        echo 'todo1 - ' . $e->getName();
    }

    /**
     * @param TestEvent $e
     */
    public function todo2(TestEvent $e)
    {
        echo 'todo2 - ' . $e->getName();
    }
}
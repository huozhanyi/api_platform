<?php

namespace Event\Test;

use \Zend\EventManager\Event;

/**
 * 测试事件
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/5/21
 * Time: 15:23
 */
class TestEvent extends Event
{
    const DO1 = 'DO1';
    const DO2 = 'DO2';
}
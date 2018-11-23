<?php

namespace Lib\Loader;
/**
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 16:18
 */
class ListenerLoader
{
    protected static $_isInstance = false;

    /**
     * @param array $listeners
     * @throws \Exception
     */
    public static function load(array $listeners)
    {
        if (!self::$_isInstance) {
            \Zend\EventManager\StaticEventManager::getInstance();//先实例化静态事件管理器，否则报错
            self::$_isInstance = true;
        }
        if ($listeners) {
            foreach ($listeners as $className) {
                if (!class_exists($className))
                    throw new \Exception('class "' . $className . '" is not exist');
                $listener = new $className();
                if (!$listener instanceof \Zend\EventManager\ListenerAggregateInterface)
                    throw new \Exception('class "' . $className . '" must instanceof \Zend\EventManager\ListenerAggregateInterface');
                \Zend\EventManager\GlobalEventManager::getEventCollection()->attach($listener);
            }
        }
    }
}
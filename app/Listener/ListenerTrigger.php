<?php

namespace Listener;
/**
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 15:51
 */
class ListenerTrigger
{
    /**
     * 触发全局监听器
     * @param string $namespace 空间名称
     * @param string $event 事件名称
     * @param object $target 目标对象/上下文
     * @param array $params 参数
     * @return array
     */
    public static function trigger($namespace, $event, $target, $params = null)
    {
        $events = \Zend\EventManager\GlobalEventManager::getEventCollection();
        $events->setIdentifiers([$namespace]);
        return $events->trigger($event, $target, $params);
    }
}
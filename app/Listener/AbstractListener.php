<?php

namespace Listener;


use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\EventManagerInterface;

/**
 * 抽象监听器，实现ListenerAggregateInterface接口
 * @author huozhanyi
 * @version V1.0 2015年6月12日 上午10:11:36
 */
abstract class AbstractListener implements ListenerAggregateInterface
{
    /**
     * @var array
     */
    protected $listeners = array();

    /**
     * 注册监听器队列
     * @see \Zend\EventManager\ListenerAggregateInterface::attach()
     */
    abstract public function attach(EventManagerInterface $events);

    /**
     * 解除监听事件
     * @see \Zend\EventManager\ListenerAggregateInterface::detach()
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->getSharedManager()->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * 抛出普通异常
     * @param string $message
     * @param string $code
     * @param string $previous
     * @throws \Exception
     */
    protected function throwNormalException($message, $code = null, $previous = null)
    {
        throw new \Exception($message, $code, $previous);
    }
}
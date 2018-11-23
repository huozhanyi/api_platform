<?php

namespace Service;
/**
 * 抽象公共服务类
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/5/10
 * Time: 11:32
 */
abstract class AbstractService
{
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
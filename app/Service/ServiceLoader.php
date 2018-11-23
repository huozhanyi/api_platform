<?php

namespace Service;

use \Lib\Loader\ClassLoader;

/**
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/5/22
 * Time: 14:32
 */
class ServiceLoader
{
    /**
     * @return \Service\Test\TestService
     */
    public static function getTestService()
    {
        return ClassLoader::instance(\Service\Test\TestService::class);
    }
}
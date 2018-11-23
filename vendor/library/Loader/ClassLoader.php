<?php

namespace Lib\Loader;
/**
 * 类单例加载器
 * 防止类重复创建
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 14:30
 */
class ClassLoader
{
    protected static $_map = array();

    private function __construct()
    {
    }

    /**
     * 实例化
     * @param string $className
     * @return mixed
     */
    public static function instance($className)
    {
        if (empty(self::$_map[$className])) {
            self::$_map[$className] = new $className();
        }
        return self::$_map[$className];
    }
}
<?php

namespace Lib\Loader;
/**
 * 配置文件加载器
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 14:12
 */
class ConfigFileLoader
{
    private static $_basePath = '';
    protected static $_map = array();

    private function __construct()
    {
    }

    /**
     * 设置配置文件基础目录
     * @param string $path
     */
    public static function setBasePath($path)
    {
        self::$_basePath = $path;
    }

    /**
     * 获取配置
     * @param string $fileName 配置文件名(多层目录可用目录结构)
     * @param string|null $key 是否返回指定键值配置
     * @return mixed
     */
    public static function get($fileName, $key = '')
    {
        if (empty(self::$_map[$fileName])) {
            self::$_map[$fileName] = require self::$_basePath . '/' . $fileName . '.php';
        }
        return $key ? self::$_map[$fileName][$key] : self::$_map[$fileName];
    }
}
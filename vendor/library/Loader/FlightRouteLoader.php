<?php

namespace Lib\Loader;
/**
 * Flight路由加载器
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 14:37
 */
class FlightRouteLoader
{
    /**
     * 加载路由
     * @param $routeConfig
     */
    public static function load($routeConfig)
    {
        $config = \Lib\Loader\ConfigFileLoader::get($routeConfig);
        foreach ($config as $row) {
            $route = $row['route'];
            $controller = $row['controller'];
            $action = empty($row['action']) ? 'index' : $row['action'];
            $method = empty($row['method']) ? 'GET' : $row['method'];
            \Flight::route($method . ' ' . $route, [ClassLoader::instance($controller), $action]);
        }
    }
}
<?php
/**
 * 启动文件
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 14:30
 */
/* 初始化配置文件 */
\Lib\Loader\ConfigFileLoader::setBasePath(PATH_ROOT . '/config');
/* 加载路由 */
\Lib\Loader\FlightRouteLoader::load('router');
//注册全局监听管理器
\Zend\EventManager\StaticEventManager::getInstance();//先实例化静态事件管理器，否则报错
$listenersConfig = \Lib\Loader\ConfigFileLoader::get('config', 'listeners');
if ($listenersConfig && is_array($listenersConfig)) {
    foreach ($listenersConfig as $className) {
        if (!class_exists($className))
            throw new \Exception('class "' . $className . '" is not exist');
        $listener = new $className();
        if (!$listener instanceof \Zend\EventManager\ListenerAggregateInterface)
            throw new \Exception('class "' . $className . '" must instanceof \Zend\EventManager\ListenerAggregateInterface');
        \Zend\EventManager\GlobalEventManager::getEventCollection()->attach($listener);
    }
}
//异常处理
Flight::map('error', function (Exception $ex) {
    //@todo 记录错误日志
    //$logger = \lib\Log::init('error/day');
    //$logger->error($ex);
    echo $ex;
    exit;
});

//@todo 404处理
Flight::map('notFound', function () {
    exit('error request!');
});


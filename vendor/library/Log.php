<?php
/**
 * 日志类
 * @author xiaoqing<xiaoqing@ivali.com>
 * @date 2017-06-26
 */

namespace Lib;

class Log
{
    private static $logger = array();

    public static function init($logFile = 'app')
    {
        if(!isset(self::$logger[$logFile]))
        {
            $logConfig = \Lib\Util::getConfig('log');
            $logPath   = $logConfig['path'];
            $logger = new \Monolog\Logger($logFile);
            $logger->pushHandler(new \Monolog\Handler\StreamHandler($logPath.$logFile.'.log'));
            $logger->pushHandler(new \Monolog\Handler\FirePHPHandler());
            self::$logger[$logFile] = $logger;
        }
        else
        {
            $logger = self::$logger[$logFile];
        }
        return $logger;
    }
}


<?php
/**
 * Redis 实例化
 * 
 * @author xiaoqing<xiaoqing@ivali.com>
 * @date 2017-06-15
 */
namespace Lib;

class Predis
{
    private static $connect = null;
    private static $connectTime = null;

    public static function init()
    {
        $connect = self::$connect;
        $connectTime = self::$connectTime;

        if ( $connectTime === null || (time() - $connectTime) >= 60 )
        {
            self::$connectTime = time();
            $cacheConfig = Util::getConfig('cache');
            $connect = self::$connect = new \Predis\Client($cacheConfig['redis'], ['prefix'=>$cacheConfig['cache_prefix']]);
        }

        return $connect;
    }
}

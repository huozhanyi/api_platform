<?php
/**
 * 实例化缓存
 * 
 * @author xiaoqing<xiaoqing@ivali.com>
 * @date 2017-06-15
 */
namespace lib;

class Cache
{
    private static $connect = [];

    public static function init($type = null)
    {
        $cacheConfig = Util::getConfig('cache');
        if ( $type === null )
        {
            $type = $cacheConfig['cache_type'];
        }

        if ( !isset(self::$connect[$type]) )
        {
            switch ( $type )
            {
                case 'apcu':
                    $adapter = new \Desarrolla2\Cache\Adapter\Apcu();
                    break;
                case 'file':
                    $adapter = new \Desarrolla2\Cache\Adapter\File($cacheConfig[$type]['path'].$cacheConfig['cache_prefix'].'/');
                    break;
                case 'memcache':
                    if ( ! extension_loaded('memcache') || ! class_exists('\Memcache') )
                    {
                        exit('The Memcache extension is not available.');
                    }
                    $backend = new \Memcache();
                    $backend->addServer($cacheConfig[$type]['host'], $cacheConfig[$type]['port']);
                    $adapter = new \Desarrolla2\Cache\Adapter\Memcache($backend);
                    $adapter->setOption('prefix', $cacheConfig['cache_prefix'].'_');
                    break;
                case 'memcached':
                    if ( ! extension_loaded('memcached') || ! class_exists('\Memcached') )
                    {
                        exit('The Memcached extension is not available.');
                    }
                    $backend = new \Memcached();
                    $backend->addServer($cacheConfig[$type]['host'], $cacheConfig[$type]['port']);
                    $adapter = new \Desarrolla2\Cache\Adapter\Memcached($backend);
                    $adapter->setOption('prefix', $cacheConfig['cache_prefix'].'_');
                    break;
                case 'memory':
                    $adapter = new \Desarrolla2\Cache\Adapter\Memory();
                    break;
                case 'redis':
                    if ( ! class_exists('\Predis\Client') )
                    {
                        exit('The predis library is not available.');
                    }
                    $backend = new \Predis\Client($cacheConfig[$type]);
                    $adapter = new \Desarrolla2\Cache\Adapter\Predis($backend);
                    $adapter->setOption('prefix', $cacheConfig['cache_prefix'].'_');
                    break;
                default:
                    $adapter = new \Desarrolla2\Cache\Adapter\NotCache();
                    break;
            }
            $connect = self::$connect[$type] = new \Desarrolla2\Cache\Cache($adapter);
        }
        else
        {
            $connect = self::$connect[$type];
        }

        return $connect;
    }
}

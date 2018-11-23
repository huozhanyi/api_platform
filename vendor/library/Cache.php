<?php
/**
 * 实例化缓存
 *
 * @author xiaoqing<xiaoqing@ivali.com>
 * @date 2017-06-15
 */

namespace Lib;

class Cache
{
    const NOT_CACHE = 'notCache';
    const APCU = 'apcu';
    const FILE = 'file';
    const MEMCACHE = 'memcache';
    const MEMCACHED = 'memcached';
    const MEMORY = 'memory';
    const REDIS = 'redis';

    private static $connect = [];

    /**
     * @param null $type
     * @return \Desarrolla2\Cache\Adapter\AdapterInterface
     */
    public static function init($type = null)
    {
        $cacheConfig = \Lib\Loader\ConfigFileLoader::get('config', 'cache');
        !$type && $type = $cacheConfig['default'];
        $cachePrefix = $cacheConfig['cache_prefix'];
        $curConfig = $cacheConfig[$type];
        //
        if (!isset(self::$connect[$type])) {
            switch ($type) {
                case self::APCU:
                    $adapter = new \Desarrolla2\Cache\Adapter\Apcu();
                    break;
                case self::FILE:
                    $adapter = new \Desarrolla2\Cache\Adapter\File($curConfig['path'] . $cachePrefix . '/');
                    break;
                case self::MEMCACHE:
                    if (!extension_loaded('memcache') || !class_exists('\Memcache')) {
                        exit('The Memcache extension is not available.');
                    }
                    $backend = new \Memcache();
                    $backend->addServer($curConfig['host'], $curConfig['port']);
                    $adapter = new \Desarrolla2\Cache\Adapter\Memcache($backend);
                    $adapter->setOption('prefix', $cachePrefix . '_');
                    break;
                case self::MEMCACHED:
                    if (!extension_loaded('memcached') || !class_exists('\Memcached')) {
                        exit('The Memcached extension is not available.');
                    }
                    $backend = new \Memcached();
                    $backend->addServer($curConfig['host'], $curConfig['port']);
                    $adapter = new \Desarrolla2\Cache\Adapter\Memcached($backend);
                    $adapter->setOption('prefix', $cachePrefix . '_');
                    break;
                case self::MEMORY:
                    $adapter = new \Desarrolla2\Cache\Adapter\Memory();
                    break;
                case self::REDIS:
                    if (!class_exists('\Predis\Client')) {
                        exit('The predis library is not available.');
                    }
                    $backend = new \Predis\Client($curConfig);
                    $adapter = new \Desarrolla2\Cache\Adapter\Predis($backend);
                    $adapter->setOption('prefix', $cachePrefix . '_');
                    break;
                default:
                    $adapter = new \Desarrolla2\Cache\Adapter\NotCache();
                    break;
            }
            self::$connect[$type] = new \Desarrolla2\Cache\Cache($adapter);
        }
        return self::$connect[$type];
    }
}

<?php
/**
 * 实例化数据库
 * 
 * @author xiaoqing<xiaoqing@ivali.com>
 * @date 2017-06-15
 */
namespace lib;

class DB
{
    private static $database = null;

    public static function init()
    {
        if ( self::$database === null )
        {
            $dbConfig = Util::getConfig('db');
            self::$database = new \Medoo\Medoo($dbConfig);
        }

        return self::$database;
    }
}

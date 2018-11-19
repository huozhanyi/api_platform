<?php

/**
 * 系统配置文件
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-08-16
 */
return [
    'flight' => [
        'base_url' => null,
        'case_sensitive' => false,
        'handle_errors' => true,
        'log_errors' => true,
        'views.path' => PATH_ROOT . 'app/view',
        'views.extension' => '.php',
    ],
    'db' => [
        'database_type' => 'mysql',
        'server' => '192.168.1.12',
        'database_name' => 'wythb_16999_com',
        'username' => 'root',
        'password' => 'aakk88DD',
        'charset' => 'utf8mb4',
    ],
    'cache' => [
        'cache_type' => 'file',
        'cache_prefix' => '51thb_16999_',
        'file' => [
            'path' => PATH_ROOT . 'storage/cache/',
        ],
        'memcache' => [
            'host' => '192.168.2.62',
            'port' => 11211,
        ],
        'memcached' => [
            'host' => '192.168.2.62',
            'port' => 11211,
        ],
        'redis' => [
            'scheme' => 'tcp',
//          'host'          =>  '192.168.2.62',
            'host' => '127.0.0.1',
            'port' => 6379,
//          'password'      =>''
        ],
        'mongodb' => [
            'host' => '192.168.2.62',
            'port' => 27017,
            'database' => 'xtl_qhb',
        ],
    ],
    'log' => [
        'path' => PATH_ROOT . 'storage/log/'
    ],
    'url' => [
        'site' => 'http://51thb.local.16999.com',
        'static' => 'http://51thb.local.16999.com',
        'cookie' => 'http://51thb.local.16999.com',
    ],
    //授权秘钥
    'secret' => [
        'thb' => '51thb_2017',
    ],
    'juhe' => [
        'ver_code_key' => 'e0bf7304f071a88fff2ecba905bea365',
    ],
    'websocket' => [
//        'on' => FALSE, //关
        'on' => TRUE, //开
        'host' => '0.0.0.0',
        'port' => '9528',
        'key_all_weal_list' => 'all_weal_list', //所有人抢到福利的记录的队列key
    ],
    'duiba' => [
        'AppKey' => 'iD39g5Zi6dkchfhiShBQAoz2Wyo',
        'AppSecret' => 'C98RM9jYPUcczKcV7ArQz7Xvuoz',
    ],
];


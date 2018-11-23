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
        'views.path' => PATH_ROOT . '/app/View',
        'views.extension' => '.phtml',
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
            'path' => PATH_ROOT . '/storage/cache/',
        ],
        'memcache' => [
            'host' => '192.168.2.62',
            'port' => 11211,
        ],
        'redis' => [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
        ]
    ],
    'listeners' => [
        \Listener\Test\TestListener::class,
    ],
    'log' => [
        'path' => PATH_ROOT . '/storage/log/'
    ],
    'view' => [
        'path' => PATH_ROOT . ''
    ]
];


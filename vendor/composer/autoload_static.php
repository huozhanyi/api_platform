<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3f8b542ad1ee41c9f47be134b9854ed6
{
    public static $files = array (
        'fc73bab8d04e21bcdda37ca319c63800' => __DIR__ . '/..' . '/mikecao/flight/flight/autoload.php',
        '5b7d984aab5ae919d3362ad9588977eb' => __DIR__ . '/..' . '/mikecao/flight/flight/Flight.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Z' => 
        array (
            'Zend\\' => 5,
        ),
        'S' => 
        array (
            'Service\\' => 8,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Predis\\' => 7,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
            'Model\\' => 6,
            'Medoo\\' => 6,
        ),
        'L' => 
        array (
            'Listener\\' => 9,
            'Lib\\' => 4,
        ),
        'E' => 
        array (
            'Event\\' => 6,
        ),
        'D' => 
        array (
            'Desarrolla2\\Test\\Cache\\' => 23,
            'Desarrolla2\\Cache\\' => 18,
        ),
        'C' => 
        array (
            'Controller\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Zend\\' => 
        array (
            0 => __DIR__ . '/..' . '/zendframework/zendframework/library/Zend',
        ),
        'Service\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app/Service',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Predis\\' => 
        array (
            0 => __DIR__ . '/..' . '/predis/predis/src',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'Model\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app/Model',
        ),
        'Medoo\\' => 
        array (
            0 => __DIR__ . '/..' . '/catfan/medoo/src',
        ),
        'Listener\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app/Listener',
        ),
        'Lib\\' => 
        array (
            0 => __DIR__ . '/..' . '/library',
        ),
        'Event\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app/Event',
        ),
        'Desarrolla2\\Test\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/desarrolla2/cache/test',
        ),
        'Desarrolla2\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/desarrolla2/cache/src',
        ),
        'Controller\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app/Controller',
        ),
    );

    public static $prefixesPsr0 = array (
        'C' => 
        array (
            'Curl' => 
            array (
                0 => __DIR__ . '/..' . '/curl/curl/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3f8b542ad1ee41c9f47be134b9854ed6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3f8b542ad1ee41c9f47be134b9854ed6::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit3f8b542ad1ee41c9f47be134b9854ed6::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}

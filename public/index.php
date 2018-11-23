<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * 入口文件
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-8-16
 */
date_default_timezone_set('Asia/Shanghai');

//定义根目录
define('PATH_ROOT', realpath(dirname(__DIR__)));

//Composer自动加载
require PATH_ROOT.'/vendor/autoload.php';

//加载路由
require '../bootstrap/app.php';

Flight::start();

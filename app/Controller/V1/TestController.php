<?php

namespace Controller\V1;
/**
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/22
 * Time: 15:55
 */
class TestController
{
    public function index()
    {
        \Service\ServiceLoader::getTestService()->do1();
    }
}
<?php

/**
 * 常用方法集合
 * 
 * @author dalin<xiaoqing@ivali.com>
 * @date 2017-08-16
 */

namespace lib;

class Util
{

    private static $config = null;

    /**
     * 获取配置
     * @param string $type 配置名
     * @return array|string|null
     */
    public static function getConfig($type = '')
    {
        if (self::$config === null)
        {
            self::$config = require PATH_ROOT . 'config/config.php';
        }

        return isset(self::$config[$type]) ? self::$config[$type] : null;
    }

    /**
     * api通用响应格式
     *
     * @param int $error 是否错误
     * @param mixed $content 返回数据 error=1时为(string)message error=0时为(array)data
     * @return array
     */
    public static function apiRes($error = 0, $content)
    {
        if ($error)
        {
            $jsonData = array(
                'error' => $error,
                'message' => $content,
                'data' => array()
            );
        } else
        {
            $jsonData = array(
                'error' => $error,
                'message' => '',
                'data' => $content
            );
        }

        return $jsonData;
    }

    /**
     * 检查是否MD5格式
     * @param string $md5
     * @return int
     */
    public static function checkMd5($md5 = '')
    {
        return preg_match("/^[a-f0-9]{32}$/", $md5);
    }

    /**
     * 检查是否是手机号码格式
     * @param int $mobile
     * @return int
     */
    public static function checkMobile($mobile = 0)
    {
        return preg_match('/^1[3|5|4|7|8][0-9]{9}$/', $mobile);
    }

    /**
     * 检查是否是QQ号码格式
     * @param int $qq
     * @return int
     */
    public static function checkQq($qq = 0)
    {
        return preg_match('/^[1-9][0-9]{4,9}$/', $qq);
    }

    /**
     * 设置接口调用频率限制
     * @param int $requestTime string $cacheKey int $cacheTime
     * @return boolen
     */
    public static function checkRequest($requestTime, $cacheKey, $cacheTime)
    {
        $cacheKey = $cacheKey . "_checkRequest";
        $cache = \lib\Cache::init();
        $requestCacheTime = $cache->get($cacheKey);
        $requestCacheTime = !empty($requestCacheTime) ? ++$requestCacheTime : 1;
        if ($requestCacheTime >= $requestTime)
        {
            return true;
        } else
        {
            $cache->set($cacheKey, $requestCacheTime, $cacheTime);
            return false;
        }
    }

    public static function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //在http 请求头加入 gzip压缩
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Encoding: gzip'));
        //curl返回的结果，采用gzip解压
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public static function ppr($param)
    {
        echo '<pre>';
        print_r($param);
    }

}

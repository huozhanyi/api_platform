<?php

/**
 * Description of TOOLS
 * @date    2018-1-30 10:28:56
 * @author wusong <wusong@ivali.com>
 */

namespace mod;

class Tools
{
    /*
     *  md5签名，$array中务必包含 appSecret
     */

    static function sign($array)
    {
        ksort($array);
        $string = "";
        while (list($key, $val) = each($array))
        {
            $string = $string . $val;
        }
        return md5($string);
    }

    /*
     *  签名验证,通过签名验证的才能认为是合法的请求
     */

    static function signVerify($appSecret, $array)
    {
        $newarray = array();
        $newarray["appSecret"] = $appSecret;
        reset($array);
        while (list($key, $val) = each($array))
        {
            if ($key != "sign")
            {
                $newarray[$key] = $val;
            }
        }
        $sign = self::sign($newarray);
        if ($sign == $array["sign"])
        {
            return true;
        }
        return false;
    }

    /*
     * 构建参数请求的URL
     */

    static function AssembleUrl($url, $array)
    {
        unset($array['appSecret']);
        foreach ($array as $key => $value)
        {
            $url = $url . $key . "=" . urlencode($value) . "&";
        }
        return $url;
    }

    /*
     *  生成自动登录地址
     *  通过此方法生成的地址，可以让用户免登录，进入积分兑换商城
     */

    static function buildCreditAutoLoginRequest($appKey, $appSecret, $uid, $credits)
    {
        $url = "http://www.duiba.com.cn/autoLogin/autologin?";
        $timestamp = time() * 1000 . "";
        $array = array("uid" => $uid, "credits" => $credits, "appSecret" => $appSecret, "appKey" => $appKey, "timestamp" => $timestamp);
        $sign = self::sign($array);
        $array['sign'] = $sign;
        $url = self::AssembleUrl($url, $array);
        return $url;
    }

    /*
     *  生成直达商城内部页面的免登录地址
     *  通过此方法生成的免登陆地址，可以通过redirect参数，跳转到积分商城任意页面
     */

    static function buildRedirectAutoLoginRequest($appKey, $appSecret, $uid, $credits, $redirect)
    {
        $url = "http://www.duiba.com.cn/autoLogin/autologin?";
        $timestamp = time() * 1000 . "";
        $array = array("uid" => $uid, "credits" => $credits, "appSecret" => $appSecret, "appKey" => $appKey, "timestamp" => $timestamp);
        if ($redirect != null)
        {
            $array['redirect'] = $redirect;
        }
        $sign = self::sign($array);
        $array['sign'] = $sign;
        $url = self::AssembleUrl($url, $array);
        return $url;
    }

    /*
     *  生成订单查询请求地址
     *  orderNum 和 bizId 二选一，不填的项目请使用空字符串
     */

    static function buildCreditOrderStatusRequest($appKey, $appSecret, $orderNum, $bizId)
    {
        $url = "http://www.duiba.com.cn/status/orderStatus?";
        $timestamp = time() * 1000 . "";
        $array = array("orderNum" => $orderNum, "bizId" => $bizId, "appKey" => $appKey, "appSecret" => $appSecret, "timestamp" => $timestamp);
        $sign = self::sign($array);
        $url = $url . "orderNum=" . $orderNum . "&bizId=" . $bizId . "&appKey=" . $appKey . "&timestamp=" . $timestamp . "&sign=" . $sign;
        return $url;
    }

    /*
     *  兑换订单审核请求
     *  有些兑换请求可能需要进行审核，开发者可以通过此API接口来进行批量审核，也可以通过兑吧后台界面来进行审核处理
     */

    static function buildCreditAuditRequest($appKey, $appSecret, $passOrderNums, $rejectOrderNums)
    {
        $url = "http://www.duiba.com.cn/audit/apiAudit?";
        $timestamp = time() * 1000 . "";
        $array = array("appKey" => $appKey, "appSecret" => $appSecret, "timestamp" => $timestamp);
        if ($passOrderNums != null && !empty($passOrderNums))
        {
            $string = null;
            while (list($key, $val) = each($passOrderNums))
            {
                if ($string == null)
                {
                    $string = $val;
                } else
                {
                    $string = $string . "," . $val;
                }
            }
            $array["passOrderNums"] = $string;
        }
        if ($rejectOrderNums != null && !empty($rejectOrderNums))
        {
            $string = null;
            while (list($key, $val) = each($rejectOrderNums))
            {
                if ($string == null)
                {
                    $string = $val;
                } else
                {
                    $string = $string . "," . $val;
                }
            }
            $array["rejectOrderNums"] = $string;
        }
        $sign = self::sign($array);
        $url = $url . "appKey=" . $appKey . "&passOrderNums=" . $array["passOrderNums"] . "&rejectOrderNums=" . $array["rejectOrderNums"] . "&sign=" . $sign . "&timestamp=" . $timestamp;
        return $url;
    }

    /*
     *  积分消耗请求的解析方法
     *  当用户进行兑换时，兑吧会发起积分扣除请求，开发者收到请求后，可以通过此方法进行签名验证与解析，然后返回相应的格式
     *  返回格式为：
     *  成功：{"status":"ok", 'errorMessage':'', 'bizId': '20140730192133033', 'credits': '100'}
     *  失败：{'status': 'fail','errorMessage': '失败原因（显示给用户）','credits': '100'}
     */

    static function parseCreditConsume($appKey, $appSecret, $request_array)
    {
        if ($request_array["appKey"] != $appKey)
        {
            throw new \Exception("appKey not match");
        }
        if ($request_array["timestamp"] == null)
        {
            throw new \Exception("timestamp can't be null");
        }
        $verify = self::signVerify($appSecret, $request_array);
        if (!$verify)
        {
            throw new \Exception("sign verify fail");
        }

        $ret = $request_array;
        return $ret;
    }

    /*
     *  加积分请求的解析方法
     *  当用点击签到，或者有签到弹层时候，兑吧会发起加积分请求，开发者收到请求后，可以通过此方法进行签名验证与解析，然后返回相应的格式
     *  返回格式为：
     *  成功：{"status":"ok", 'errorMessage':'', 'bizId': '20140730192133033', 'credits': '100'}
     *  失败：{'status': 'fail','errorMessage': '失败原因（显示给用户）','credits': '100'}
     */

    static function addCreditsConsume($appKey, $appSecret, $request_array)
    {
        if ($request_array["appKey"] != $appKey)
        {
            throw new \Exception("appKey not match");
        }
        if ($request_array["timestamp"] == null)
        {
            throw new \Exception("timestamp can't be null");
        }
        $verify = self::signVerify($appSecret, $request_array);
        if (!$verify)
        {
            throw new \Exception("sign verify fail");
        }

        $ret = $request_array;
        return $ret;
    }

    /*
     *  虚拟商品充值的解析方法
     *  当用兑换虚拟商品时候，兑吧会发起虚拟商品请求，开发者收到请求后，可以通过此方法进行签名验证与解析，然后返回相应的格式
     *  返回格式为：
     *   成功：   {status:"success",credits:"10", supplierBizId:"no123456"}
     * 	处理中： {status:"process ",credits:"10" , supplierBizId:"no123456"}
     * 	失败：   {status:"fail ", errorMessage:"签名签证失败", supplierBizId:"no123456"}
     */

    static function virtualRecharge($appKey, $appSecret, $request_array)
    {
        if ($request_array["appKey"] != $appKey)
        {
            throw new \Exception("appKey not match");
        }
        if ($request_array["timestamp"] == null)
        {
            throw new \Exception("timestamp can't be null");
        }
        $verify = self::signVerify($appSecret, $request_array);
        if (!$verify)
        {
            throw new \Exception("sign verify fail");
        }

        $ret = $request_array;
        return $ret;
    }

    /*
     *  兑换订单的结果通知请求的解析方法
     *  当兑换订单成功时，兑吧会发送请求通知开发者，兑换订单的结果为成功或者失败，如果为失败，开发者需要将积分返还给用户
     */

    static function parseCreditNotify($appKey, $appSecret, $request_array)
    {
        if ($request_array["appKey"] != $appKey)
        {
            throw new \Exception("appKey not match");
        }
        if ($request_array["timestamp"] == null)
        {
            throw new \Exception("timestamp can't be null");
        }
        $verify = self::signVerify($appSecret, $request_array);
        if (!$verify)
        {
            throw new \Exception("sign verify fail");
        }
        $ret = array("success" => $request_array["success"], "errorMessage" => $request_array["errorMessage"], "bizId" => $request_array["bizId"]);
        return $ret;
    }

}

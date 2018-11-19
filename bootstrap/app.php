<?php

/**
 * 路由配置文件
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-08-16
 */
/* 路由开始 */
Flight::route('POST /api/login', ['\ctl\Hb', 'login']); //授权登录接口
Flight::route('GET /api/snatch/@type:[0-9]+|-[1-9]+', ['\ctl\Hb', 'snatch']); //执行抢红包
Flight::route('GET /api/user', ['\ctl\Hb', 'user']); //我的红包
Flight::route('GET /api/record/@page:[0-9]+', ['\ctl\Hb', 'record']); //我的红包记录
Flight::route('GET /api/list/@type:friend|official', ['\ctl\Hb', 'getList']); //红包列表
Flight::route('GET /api/confirm/@hbid:[0-9]+', ['\ctl\Hb', 'confirm']); //分享完成之后确认
Flight::route('GET /api/version/@system:[1|2|3]', ['\ctl\Hb', 'version']); //查询版本信息接口
Flight::route('POST /api/deposit', ['\ctl\Hb', 'deposit']); //提现接口
Flight::route('GET /api/depositlist/@page:[0-9]+', ['\ctl\Hb', 'depositList']); //我的提现记录
Flight::route('GET /api/alldepositlist', ['\ctl\Hb', 'alldepositList']); //全部提现记录
Flight::route('GET /api/recently', ['\ctl\Hb', 'recently']); //最近购买记录
Flight::route('POST /api/vercode', ['\ctl\Hb', 'vercode']); //请求验证码接口
Flight::route('GET /api/config/', ['\ctl\Hb', 'config']); //获取用户配置设置

//派派2.0 wusong 180115
Flight::route('POST /api2/login', ['\ctl\Hb2', 'login']); //授权登录接口
Flight::route('GET /api2/snatch/@type:[0-9]+|-[1-9]+', ['\ctl\Hb2', 'snatch']); //执行抢红包
Flight::route('GET /api2/user', ['\ctl\Hb2', 'user']); //我的红包
Flight::route('GET /api2/record/@page:[0-9]+', ['\ctl\Hb2', 'record']); //我的红包记录
Flight::route('GET /api2/list/@type:friend|official', ['\ctl\Hb2', 'getList']); //红包列表
Flight::route('GET /api2/confirm/@hbid:[0-9]+', ['\ctl\Hb2', 'confirm']); //分享完成之后确认
Flight::route('GET /api2/version/@system:[1|2|3]', ['\ctl\Hb2', 'version']); //查询版本信息接口
Flight::route('POST /api2/deposit', ['\ctl\Hb2', 'deposit']); //提现接口
Flight::route('GET /api2/depositlist/@page:[0-9]+', ['\ctl\Hb2', 'depositList']); //我的提现记录
Flight::route('GET /api2/alldepositlist', ['\ctl\Hb2', 'alldepositList']); //全部提现记录
Flight::route('GET /api2/recently', ['\ctl\Hb2', 'recently']); //最近购买记录
Flight::route('POST /api2/vercode', ['\ctl\Hb2', 'vercode']); //请求验证码接口
Flight::route('GET /api2/config/', ['\ctl\Hb2', 'config']); //获取用户配置设置
//Flight::route('GET /api/user', ['\ctl\Hb', 'user']);//我的红包，旧接口增加	goldcoin 金币数 ，goldcount 领取金币记录
//Flight::route('GET /api/depositlist/@page:[0-9]+', ['\ctl\Hb', 'depositList']);//我的提现记录
//Flight::route('GET /api/alldepositlist', ['\ctl\Hb', 'alldepositList']);//全部提现记录
Flight::route('GET /api2/configgoldprob/', ['\ctl\Hb2', 'configGoldProb']); //获取金币概率设置 
Flight::route('GET /api2/weal/@now:[0-9]+', ['\ctl\Hb2', 'weal']); //获取福利
Flight::route('GET /api2/signed/@now:[0-9]+', ['\ctl\Hb2', 'signed']); //签到领金币
Flight::route('GET /api2/recordgold/@page:[0-9]+', ['\ctl\Hb2', 'recordGold']); //我的金币记录
Flight::route('GET /api2/allrecordgoldhb/@page:[0-9]+', ['\ctl\Hb2', 'allRecordGoldHb']); //全部金币红包记录
Flight::route('GET /api2/ads', ['\ctl\Hb2', 'adslist']); //获取后台广告列表
//积分商城部分
Flight::route('GET /api2/redirecturl', ['\ctl\Hb2', 'getRedirectUrl']); //获取重定向积分商城地址
Flight::route('GET /api2/deductpoints', ['\ctl\Hb2', 'deductPoints']); //扣积分接口
Flight::route('GET /api2/affirmpoints', ['\ctl\Hb2', 'affirmPoints']); //确认扣积分接口

Flight::route('GET /api2/testws', ['\ctl\Hb2', 'testws']); //测试ws接口

//Flight::route('POST /api2/expense', ['\ctl\Hb2', 'expense']); //消费金币,接入了兑吧后，目前无实际用处
//派派2.0结束 wusong 180115

//无限分身的接口部分
Flight::route('GET /api/appversion', ['\ctl\Hb2', 'wxfs_appversion']); //
Flight::route('GET /api/modelversion', ['\ctl\Hb2', 'wxfs_modelversion']); //

//--------内部调用接口------------------
Flight::route('GET /cache/delete/@cacheKey', ['\ctl\Hb', 'delCache']); //删除缓存接口

/* 路由结束 */

//异常处理
Flight::map('error', function(Exception $ex)
{
    //@todo 记录错误日志
    //$logger = \lib\Log::init('error/day');
    //$logger->error($ex);
    echo $ex;
    exit;
});

//@todo 404处理
Flight::map('notFound', function()
{
    exit('error request!');
});


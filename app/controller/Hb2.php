<?php

/**
 * 我要偷红包控制器
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-08-16
 */

namespace ctl;

class Hb2
{

    /**
     * 授权登录接口
     */
    public static function login()
    {
        $request = \Flight::request();
        $data['ip'] = $request->ip;
        $data['openid'] = isset($request->data['openid']) ? addslashes(trim($request->data['openid'])) : '';
        $data['nickname'] = isset($request->data['nickname']) ? addslashes(trim($request->data['nickname'])) : '';
        $data['sex'] = isset($request->data['sex']) ? (int) $request->data['sex'] : 0;
        $data['headimgurl'] = isset($request->data['headimgurl']) ? $request->data['headimgurl'] : '';
        $data['type'] = isset($request->data['type']) ? trim($request->data['type']) : 'wx';
        $data['system'] = isset($request->data['system']) ? trim($request->data['system']) : 1;
        $data['lon'] = isset($request->data['lon']) ? trim($request->data['lon']) : null;
        $data['lat'] = isset($request->data['lat']) ? trim($request->data['lat']) : null;
        $data['uptime'] = time();
        $ipArea = \lib\IP::find($data['ip']);
        if (isset($ipArea[2]))
        {
            $data['city'] = $ipArea[2];
        }

        //获取当前地址
        if (!empty($data['lon']) && !empty($data['lat']))
        {
            $data['path'] = \mod\Hb2::getPath($data['lon'], $data['lat']);
        } else
        {
            $data['path'] = NULL;
        }

        //关联好友用户ID
        $friendId = isset($request->data['uid']) ? (int) $request->data['uid'] : 0;

        if (!$data['openid'] || (strlen($data['openid']) != 28 && strlen($data['openid']) != 32))
        {
            \Flight::json(\lib\Util::apiRes(1, 'OPENID_ERROR'));
            exit();
        }

        if ($data['type'] != 'qq' && $data['type'] != 'wx')
        {
            \Flight::json(\lib\Util::apiRes(1, 'TYPE_ERROR'));
            exit();
        }

        //查看用户是否已经入库
        $userId = \mod\Hb2::getIdByOpenid($data['openid']);
        if (!$userId)
        {
            //注册用户
            $userId = \mod\Hb2::addUser($data);
            if (!$userId)
            {
                \Flight::json(\lib\Util::apiRes(1, 'LOGIN_ERROR'));
                exit();
            }
            $isNewUser = 1; // 只有第一次登录的人才给邀请人金币
        } else
        {

            $udata = array(
                'ip' => $data['ip'],
                'nickname' => $data['nickname'],
                'sex' => $data['sex'],
                'headimgurl' => $data['headimgurl'],
                'city' => $data['city'],
                'uptime' => $data['uptime'],
                'lon' => $data['lon'], //经度
                'lat' => $data['lat'], //纬度
                'path' => $data['path'], //地址
            );

            $rs = \mod\Hb2::editUser($userId, $udata);
        }

        //生成openid对应唯一token
        $token = \mod\Hb2::setToken($userId, $data['openid']);

        $result = array(
            'nickname' => $data['nickname'],
            'sex' => $data['sex'],
            'headimgurl' => $data['headimgurl'],
            'user_id' => $userId,
            'token' => $token,
//            'lon' => empty($data['lon']) ? '' : $data['lon'], //经度
//            'lat' => empty($data['lat']) ? '' : $data['lat'], //纬度
            'path' => empty($data['path']) ? '' : $data['path'], //地址
        );

        //关联用户
        if ($friendId)
        {
            //将邀请人和被邀请人相互关联
            \mod\Hb2::relateFriends($friendId, $userId);

            //新人才给邀请人金币
            if (!empty($isNewUser))
            {
                $result['goldcoin'] = \mod\Hb2::gainGold($userId, 3, $friendId);
            }
        }

        $result['signed'] = 0; //默认未签到
        //非新用户要判断是否已签到过uptime
        if (empty($isNewUser))
        {
            //上次签到记录
            $preRecordGold = \mod\Hb2::getPreRecordGold($userId, 2, 0);

            //按今天日期来比对
            if (!empty($preRecordGold) && date('ymd', (int) $preRecordGold['itime']) == date('ymd'))
            {
                $result['signed'] = 1; //已签到
            }
        }

        \Flight::json(\lib\Util::apiRes(0, $result));
    }

    /**
     * 执行抢红包
     */
    public static function snatch($type = 0)
    {
        //红包类型
        $param['type'] = $type;
        $userId = \mod\Hb2::checkAuth($param);

        //访问接口太频繁
        if (!\mod\Hb2::checkWealAct($userId, time(), '0_1'))
        {
            \Flight::json(\lib\Util::apiRes(1, 'SNATCH_OFTEN'));
            exit;
        }

        $data = \mod\Hb2::snatchHb($userId, $param['type']);

        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'SNATCH_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 分享完后确认红包入库
     */
    public static function confirm($hbid)
    {
        //红包ID
        $param['hbid'] = $hbid;
        $userId = \mod\Hb2::checkAuth($param);
        $data = \mod\Hb2::confirmHb($userId, $param['hbid']);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'CONFIRM_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 获取我的红包信息
     * 增加金币个数字段 wusong 180115
     */
    public static function user()
    {
        $userId = \mod\Hb2::checkAuth();
        $data = \mod\Hb2::getUser($userId);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_USER'));
        } else
        {
            $result = array(
                'user_id' => $data['user_id'],
                'nickname' => $data['nickname'],
                'sex' => $data['sex'],
                'headimgurl' => $data['headimgurl'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'ip' => $data['ip'],
                'status' => $data['status'],
                'gold' => $data['goldcoin'],
                'lon' => empty($data['lon']) ? '' : $data['lon'], //经度
                'lat' => empty($data['lat']) ? '' : $data['lat'], //纬度
                'path' => empty($data['path']) ? '' : $data['path'], //纬度
            );

            //补充得到地址
            if (empty($result['path']) && !empty($result['lon']) && !empty($result['lat']))
            {
                $result['path'] = \mod\Hb2::getPath($result['lon'], $result['lat']);
            } else
            {
                $result['path'] = '';
            }

            //上次签到记录
            $preRecordGold = \mod\Hb2::getPreRecordGold($userId, 2, 0);

            //按今天日期来比对
            if (!empty($preRecordGold) && date('ymd', (int) $preRecordGold['itime']) == date('ymd'))
            {
                $result['signed'] = 1; //已签到
            } else
            {
                $result['signed'] = 0; //默认未签到
            }

            \Flight::json(\lib\Util::apiRes(0, $result));
        }
    }

    /**
     * 我的红包记录
     */
    public static function record($page)
    {
        $userId = \mod\Hb2::checkAuth();
        $data = \mod\Hb2::getRecord($userId, $page);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 我的金币记录
     */
    public static function recordGold($page)
    {
        $userId = \mod\Hb2::checkAuth();
        $data = \mod\Hb2::getRecordGold($userId, $page);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 全部金币红包记录
     */
    public static function allRecordGoldHb($page = 0)
    {
//        $userId = \mod\Hb2::checkAuth();
        $data = \mod\Hb2::getAllRecordGoldHb($page);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 广告列表
     */
    public static function adslist()
    {
//        $data = \mod\Hb2::getRecordGold($userId, $page);
        $data = \mod\Hb2::getAdslist();
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_ADS'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 红包列表
     */
    public static function getList($type)
    {
        $userId = \mod\Hb2::checkAuth();
        if ($type == 'friend')
        {
            $data = \mod\Hb2::getFriends($userId);
        } else
        {
            $data = \mod\Hb2::getOfficial($userId);
        }
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /*
     * 应用更新接口
     */

    public static function version($system)
    {
        $data = \mod\Hb2::getVersion($system);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 删除缓存接口（内部调用）
     */
    public static function delCache($cacheKey = '')
    {
        $cache = \lib\Cache::init();
        $cache->delete($cacheKey);
        exit('ok');
    }

    /**
     * 提现
     */
    public static function deposit()
    {
        $request = \Flight::request();
        //为了防止造成字符串不统一照成的加密不匹配，故获取money不必转换为浮点型
        $data['money'] = isset($request->data['money']) ? trim($request->data['money']) : 0;
        $data['mobile'] = isset($request->data['mobile']) ? (int) trim($request->data['mobile']) : 0;
        $data['vercode'] = isset($request->data['vercode']) ? (int) trim($request->data['vercode']) : 0;
        $data['name'] = isset($request->data['name']) ? addslashes(trim($request->data['name'])) : '';
        $data['account'] = isset($request->data['account']) ? addslashes(trim($request->data['account'])) : '';

        if (!$data['money'] || !$data['mobile'] || !$data['account'])
        {
            \Flight::json(\lib\Util::apiRes(1, 'PARAM_ERROR'));
            exit();
        }

        //验证手机号
        if (!preg_match("/^1[34578]\d{9}$/", $data['mobile']))
        {
            \Flight::json(\lib\Util::apiRes(1, 'MOBILE_ERROR'));
            exit();
        }

        //初始化缓存类
        $cache = \lib\Cache::init();
        $vercode = $cache->get('vercode_' . $data['mobile']);
        if ($data['vercode'] != $vercode)
        {
            \Flight::json(\lib\Util::apiRes(1, 'VERCODE_ERROR'));
            exit();
        }

        $userId = \mod\Hb2::checkAuth($data);
        $rdata = \mod\Hb2::deposit($userId, $data);
        if (!$rdata)
        {
            \Flight::json(\lib\Util::apiRes(1, 'DEPOSIT_ERROR'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $rdata));
        }
    }

    /**
     * 请求验证码接口
     */
    public static function vercode()
    {
        //获取电话号码
        $request = \Flight::request();
        $mobile = $param['mobile'] = isset($request->data['mobile']) ? (int) trim($request->data['mobile']) : 0;
        if (!preg_match("/^1[34578]\d{9}$/", $mobile))
        {
            \Flight::json(\lib\Util::apiRes(1, 'MOBILE_ERROR'));
            exit();
        }

        $userId = \mod\Hb2::checkAuth($param);

        //初始化缓存类
        $cache = \lib\Cache::init();

        //每个账号每天只能发5条验证信息
        $todayTimes = $cache->get('vercode_limit_' . $userId);
        $todayTimes = $todayTimes ? $todayTimes : 0;
        if ($todayTimes >= 5)
        {
            \Flight::json(\lib\Util::apiRes(1, 'SEND_TOO_MORE'));
            exit();
        }

        //上一个验证码未失效 继续使用并延长有效期
        $vCode = $cache->get('vercode_' . $mobile);
        if (!$vCode)
        {
            //否则生成验证码
            $vCode = mt_rand(100000, 999999);
        }

        //获取验证码
        $resData = \mod\Hb2::getVerCode($mobile, 51541, "#code#={$vCode}");
        if (!$resData)
        {
            \Flight::json(\lib\Util::apiRes(1, 'API_ERROR'));
            exit();
        }

        //发送失败
        if ($resData['error_code'])
        {
            \Flight::json(\lib\Util::apiRes($resData['error_code'], 'VERCODE_ERROR'));
            exit();
        }

        //创建验证码缓存
        $cache->set('vercode_' . $mobile, $vCode, 600); //10分钟有效期
        //记录该账号今天发的条数
        $cache->set('vercode_limit_' . $userId, ++$todayTimes, 86400);

        \Flight::json(\lib\Util::apiRes(0, $resData['result']));
    }

    /**
     * 我的提现记录列表
     */
    public static function depositList($page)
    {

        $userId = \mod\Hb2::checkAuth();
        $data = \mod\Hb2::getDepositList($userId, $page);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 全部提现记录列表
     */
    public static function alldepositList()
    {
//        $userId = \mod\Hb2::checkAuth();
        $data = \mod\Hb2::getAllDepositList();
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 最近抢到红包记录
     */
    public static function recently()
    {
        $data = \mod\Hb2::getRecently();
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /*
     * 获取段位设置
     */

    public static function config()
    {
        $config = \mod\Hb2::getConfig("mini_program");
        $data['qrcode']['url'] = $config['qrcode'];
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    //获得领金币的概率 wusong 180115
    public static function configGoldProb()
    {
//        $userId = \mod\Hb2::checkAuth();
        $config = \mod\Hb2::getConfig("gold_prob");
        $data['ext1'] = $config['ext1'];
//        $data['ext2'] = $config['ext2'];
//        $data['ext3'] = $config['ext3'];
//        $data['ext4'] = $config['ext4'];
//        $data['ext5'] = $config['ext5'];
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EMPTY_DATA'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
        exit;
    }

    //领取福利，里面概率判断是红包还是金币
    public static function weal($now)
    {
        if (empty($now) || $now < 0 || abs(time() - $now) > 100000)
        {
            \Flight::json(\lib\Util::apiRes(1, 'WEAL_FAIL'));
            exit;
        }
        $userId = \mod\Hb2::checkAuth(['now' => $now]);
        $isgold = TRUE; //默认取金币
        $type = 1; //金币
        $typesub = 1; //直接金币
        //先取红包or金币的概率值
        $config = \mod\Hb2::getConfig("gold_prob");
        $config = array_filter($config);

        //空值，非数值，超出0~1范围的数均为无效，取默认数 
        if (!isset($config['ext2']) || !is_numeric($config['ext2']) || $config['ext2'] < 0 || $config['ext2'] > 1)
        {
            $config['ext2'] = 0.5000; //默认取金币的概率
        }
        //随机数超出是金币的数的范围
        if ($config['ext2'] >= 0 && $config['ext2'] <= 1 && ($config['ext2'] == 0 || rand(1, 10000) > $config['ext2'] * 10000))
        {
            $isgold = FALSE;
            $type = 0; //红包
            $typesub = 2; //非限时红包
        }

        //领取太频繁
        if (!\mod\Hb2::checkWealAct($userId, $now, $type . '_' . $typesub))
        {
            \Flight::json(\lib\Util::apiRes(1, 'WEAL_OFTEN'));
            exit;
        }

        if ($isgold)
        {
            //直接领金币
            self::gain($userId, 1, 0);
        } else
        {
            //直接领取红包
            $data = \mod\Hb2::snatchWealHb($userId);

            if (!$data)
            {
                \Flight::json(\lib\Util::apiRes(1, 'SNATCH_HB_FAIL'));
            } else
            {
                \Flight::json(\lib\Util::apiRes(0, $data));
            }
            exit;
        }
    }

    /**
     * 签到
     * @param type $param
     */
    public static function signed($now)
    {
        if (empty($now) || $now < 0 || abs(time() - $now) > 10)
        {
            \Flight::json(\lib\Util::apiRes(1, 'SIGNED_FAIL'));
            exit;
        }

        $userId = \mod\Hb2::checkAuth(['now' => $now]);

        //领取太频繁
        if (!\mod\Hb2::checkWealAct($userId, $now, '1_2')) //2是签到领
        {
            \Flight::json(\lib\Util::apiRes(1, 'SIGNED_OFTEN'));
            exit;
        }

        //签到领金币
        self::gain($userId, 2, 0);
    }

    /**
     * 消费金币
     */
    public static function expense()
    {
        $request = \Flight::request();
        $type = 1; //金币
        //消费金币数
        $goldcoin = isset($request->query['goldcoin']) ? abs((int) $request->query['goldcoin']) : 0;

        //消费类型 11 默认消费 12 待设定
        $expense_type = isset($request->query['type']) ? (int) $request->query['type'] : 11;

        //带上红包数量鉴权
        $userId = \mod\Hb2::checkAuth(['goldcoin' => $goldcoin, 'type' => $expense_type]);

        //消费太频繁
        if (!\mod\Hb2::checkExpenseAct($userId, time(), $expense_type))
        {
            \Flight::json(\lib\Util::apiRes(1, 'EXPENSE_OFTEN'));
            exit;
        }

        $data = \mod\Hb2::expenseGd($userId, $goldcoin, $type . '_' . $expense_type); //金币1 消费11

        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'EXPENSE_GD_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
        exit;
    }

    /**
     * 执行领金币，type ：1 直接领 2 签到领 3邀请领
     */
    private static function gain($userId, $type = 1, $friend = 0)
    {
        $data = \mod\Hb2::gainGold($userId, $type, $friend);

        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'SNATCH_GD_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
        exit;
    }

    //==============================接入兑吧积分商城

    /**
     * 获取重定向积分商城地址
     */
    public static function getRedirectUrl()
    {
        //获得当前用户id
        $userId = \mod\Hb2::checkAuth();
        //获取用户信息
        $userInfo = \mod\Hb2::getUser($userId);
        $config_duiba = \lib\Util::getConfig('duiba');

        $data['uid'] = $userId;
        $data['goldcoin'] = $userInfo['goldcoin'];
        $data['redirectUrl'] = \mod\Hb2::buildCreditAutoLoginRequest($config_duiba['AppKey'], $config_duiba['AppSecret'], $data['uid'], $data['goldcoin']);

        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'GET_REDIRECTURL_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
        exit;
    }

    /**
     * 消费金币，取无效状态，根据返回值确认
     */
    public static function deductPoints()
    {
        $request = \Flight::request();

        $request_array = null;
        if (!empty($request->query))
        {
            foreach ($request->query as $key => $value)
            {
                $request_array[$key] = $value;
            }
        }

        //记录消费
        $logger = \lib\Log::init('deduct/' . date('ymd'));
        $logger->debug('RES', ['user_id' => $request_array['uid'], 'response' => json_encode($request_array)]);

        $bizId = $credits = 0;
        $apiRes['status'] = 'fail'; // 默认错误
        if (!empty($request_array))
        {
            $config_duiba = \lib\Util::getConfig('duiba');
            //验证参数正常，说明是兑吧的请求
            $verify = \mod\Hb2::parseCreditConsume($config_duiba['AppKey'], $config_duiba['AppSecret'], $request_array);
            if (!empty($verify[0]) && $verify[0] == 'ok')
            {
                //验证成功为兑吧订单，下面进行本地订单生成和扣除金币
                $re_order = \mod\Hb2::addDuibaOrder($request_array['uid'], $request_array);

                if (!empty($re_order[0]) && $re_order[0] == 'ok')
                {
                    $apiRes['status'] = 'ok';
                    $apiRes['errorMessage'] = '';
                    $bizId = $re_order[1];
                    $credits = $re_order[2];
                } else
                {
                    $apiRes['errorMessage'] = $re_order[1];
                }
            } else
            {
                $apiRes['errorMessage'] = $verify[1];
            }
        } else
        {
            $apiRes['errorMessage'] = 'parameter error'; // 参数错误
        }

        $apiRes['bizId'] = $bizId;
        $apiRes['credits'] = $credits; //用户积分余额,即金币余额
        //记录返回
        $logger->debug('RES', ['user_id' => $request_array['uid'], 'response' => json_encode($apiRes)]);

        \Flight::json($apiRes);
        exit;
    }

    /**
     * 确认是消费金币还是还原金币
     */
    public static function affirmPoints()
    {
        $request = \Flight::request();

        $request_array = null;
        if (!empty($request->query))
        {
            foreach ($request->query as $key => $value)
            {
                $request_array[$key] = $value;
            }
        }

        $echo = 'error';

        //记录确认消费
        $logger = \lib\Log::init('affirm/' . date('ymd'));
        $logger->debug('RES', ['bizId' => $request_array['bizId'], 'response' => json_encode($request_array)]);

        $config_duiba = \lib\Util::getConfig('duiba');

        //获得确认数据的验证
        $re_order = \mod\Hb2::parseCreditNotify($config_duiba['AppKey'], $config_duiba['AppSecret'], $request_array);

        //兑换是否成功，状态是boolean的true和false
        if ($re_order[0])
        {
            //确认消费并扣除金币
            $re = \mod\Hb2::affirmDuibaOrder($request_array);

            if (!empty($re['ret']) && $re['ret'] == 1)
            {
                //成功
                $echo = 'ok';
            }
        }

        echo $echo;

        //记录返回
        $logger->debug('RES', ['bizId' => $request_array['bizId'], 'response' => $echo]);

        exit;
    }

    //=============================下面是无限分身的接口，因为单独，所以写在这里

    /**
     * 模块更新
     */
    public static function wxfs_modelversion()
    {
        self::wxfs_config('modelversion');
    }

    /**
     * 主体更新
     */
    public static function wxfs_appversion()
    {
        self::wxfs_config('appversion');
    }

    private static function wxfs_config($typename)
    {
        $data = \mod\Hb2::wxfsConfig($typename);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'GET_CONFIG_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
        exit;
    }

    public static function testws()
    {
        $config_cache = \lib\Util::getConfig('cache');
        $config_cache['redis']['persistent'] = 1;
        $cache = new \Predis\Client($config_cache['redis'], ['prefix' => $config_cache['cache_prefix']]);

        $config_websocket = \lib\Util::getConfig('websocket');
        $cacheListKey = $config_websocket['key_all_weal_list'];


//        $msg = $cache->rpop($cacheListKey);
//        \lib\Util::ppr($msg);

        $msglen = $cache->llen($cacheListKey);
        \lib\Util::ppr($msglen);

//        $cache->ltrim($cacheListKey, -1, -100);
//        $msglen = $cache->llen($cacheListKey);
//        \lib\Util::ppr($msglen);
    }

}

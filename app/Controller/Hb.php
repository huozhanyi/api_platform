<?php
/**
 * 我要偷红包控制器
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-08-16
 */
namespace Controller;

class Hb
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
        $data['uptime'] = time();
        $ipArea = \lib\IP::find($data['ip']);
        if ( isset($ipArea[2]) )
        {
            $data['city'] = $ipArea[2];
        }
        
        
        //关联好友用户ID
        $friendId = isset($request->data['uid']) ? (int) $request->data['uid'] : 0;
        
        if ( !$data['openid'] || (strlen($data['openid']) != 28 && strlen($data['openid']) != 32) )
        {
            \Flight::json(\lib\Util::apiRes(1, 'OPENID_ERROR'));
            exit();
        }
        
        if ( $data['type'] != 'qq' && $data['type'] != 'wx' )
        {
            \Flight::json(\lib\Util::apiRes(1, 'TYPE_ERROR'));
            exit();
        }
        
        //查看用户是否已经入库
        $userId = \Model\Hb::getIdByOpenid($data['openid']);
        if ( !$userId )
        {   
            //注册用户
            $userId = \Model\Hb::addUser($data);
            if ( !$userId )
            {
                \Flight::json(\lib\Util::apiRes(1, 'LOGIN_ERROR'));
                exit();
            }
            
        }else{
            $udata  = array(
                'ip'=>$data['ip'],
                'nickname'=>$data['nickname'],
                'sex'=>$data['sex'],
                'headimgurl'=>$data['headimgurl'],
                'city'=>$data['city'],
                'uptime'=>$data['uptime']
            );
            
            $rs = \Model\Hb::editUser($userId, $udata);
        }
            
            //生成openid对应唯一token
            $token = \Model\Hb::setToken($userId,$data['openid']);
            
            $result = array(
                'nickname' => $data['nickname'],
                'sex' => $data['sex'],
                'headimgurl' => $data['headimgurl'],
                'user_id' => $userId,
                'token' => $token  
            );
            
            //关联用户
            if($friendId)
            {
                \Model\Hb::relateFriends($friendId,$userId);
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
        $userId = \Model\Hb::checkAuth($param);
        $data = \Model\Hb::snatchHb($userId,$param['type']);
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'SNATCH_FAIL' ));
        }else{
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
        $userId = \Model\Hb::checkAuth($param);
        $data = \Model\Hb::confirmHb($userId,$param['hbid']);
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'CONFIRM_FAIL' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
    
    /**
     * 获取我的红包信息
     */
    public static function user()
    {
        $userId = \Model\Hb::checkAuth();
        $data = \Model\Hb::getUser($userId);
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_USER' ));
        }else{
            $result = array(
                'user_id' => $data['user_id'],
                'nickname' => $data['nickname'],
                'sex' => $data['sex'],
                'headimgurl' => $data['headimgurl'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'ip' => $data['ip'],
                'status' => $data['status'],
            );
            \Flight::json(\lib\Util::apiRes(0, $result));
        }
    }
    
    /**
     * 我的红包记录
     */
    public static function record($page)
    {
        $userId = \Model\Hb::checkAuth();
        $data = \Model\Hb::getRecord($userId,$page);
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
    /**
     * 红包列表
     */
    public static function getList($type)
    {
        $userId = \Model\Hb::checkAuth();
        if($type == 'friend')
        {
            $data = \Model\Hb::getFriends($userId);
        }else{
            $data = \Model\Hb::getOfficial($userId);
        }
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
    /*
     * 应用更新接口
     */
    public static function version($system)
    {
        $data = \Model\Hb::getVersion($system);
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }

    /**
     * 删除缓存接口（内部调用）
     */
    public static function delCache( $cacheKey = '' )
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
        $data['mobile'] = isset($request->data['mobile']) ? (int)trim($request->data['mobile']) : 0;
        $data['vercode'] = isset($request->data['vercode']) ? (int)trim($request->data['vercode']) : 0;
        $data['name'] = isset($request->data['name']) ? addslashes(trim($request->data['name'])) : '';
        $data['account'] = isset($request->data['account']) ? addslashes(trim($request->data['account'])) : '';
        
        if ( !$data['money'] || !$data['mobile'] || !$data['account'])
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
        $vercode = $cache->get('vercode_'.$data['mobile']);
        if($data['vercode'] != $vercode)
        {
            \Flight::json(\lib\Util::apiRes(1, 'VERCODE_ERROR'));
            exit();
        }
        
        $userId = \Model\Hb::checkAuth($data);
        $rdata = \Model\Hb::deposit($userId,$data);
        if ( !$rdata )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'DEPOSIT_ERROR' ));
        }else{
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
        $mobile = $param['mobile'] = isset($request->data['mobile']) ? (int)trim($request->data['mobile']) : 0;
        if (!preg_match("/^1[34578]\d{9}$/", $mobile))
        {
            \Flight::json(\lib\Util::apiRes(1, 'MOBILE_ERROR'));
            exit();
        }
        
        $userId = \Model\Hb::checkAuth($param);

        //初始化缓存类
        $cache = \lib\Cache::init();

        //每个账号每天只能发5条验证信息
        $todayTimes = $cache->get('vercode_limit_'.$userId);
        $todayTimes = $todayTimes ? $todayTimes : 0;
        if($todayTimes >= 5)
        {
            \Flight::json(\lib\Util::apiRes(1, 'SEND_TOO_MORE'));
            exit();
        }
        
        //上一个验证码未失效 继续使用并延长有效期
        $vCode = $cache->get('vercode_'.$mobile);
        if(!$vCode)
        {
            //否则生成验证码
            $vCode = mt_rand(100000, 999999);
        }
        
        //获取验证码
        $resData = \Model\Hb::getVerCode($mobile,51541,"#code#={$vCode}");
        if ( !$resData )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'API_ERROR' ));
            exit();
        }
        
        //发送失败
        if($resData['error_code'])
        {
            \Flight::json(\lib\Util::apiRes($resData['error_code'], 'VERCODE_ERROR'));
            exit();
        }
        
        //创建验证码缓存
        $cache->set('vercode_'.$mobile, $vCode, 600);//10分钟有效期

        //记录该账号今天发的条数
        $cache->set('vercode_limit_'.$userId, ++$todayTimes, 86400);

        \Flight::json(\lib\Util::apiRes(0, $resData['result']));
    }
    
    /**
     * 我的提现记录列表
     */
    public static function depositList($page)
    {
        $userId = \Model\Hb::checkAuth();
        $data = \Model\Hb::getDepositList($userId,$page);
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
    /**
     * 全部提现记录列表
     */
    public static function alldepositList()
    {
        $userId = \Model\Hb::checkAuth();
        $data = \Model\Hb::getAllDepositList();
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
    /**
     * 最近抢到红包记录
     */
    public static function recently()
    {
        $data = \Model\Hb::getRecently();
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
    /*
     * 获取段位设置
     */
    public static function config()
    {
        $config = \Model\Hb::getConfig("mini_program");
        $data['qrcode']['url'] = $config['qrcode'];
        if ( !$data )
        {
            \Flight::json(\lib\Util::apiRes( 1, 'EMPTY_DATA' ));
        }else{
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
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
        $data = \Model\Hb::wxfsConfig($typename);
        if (!$data)
        {
            \Flight::json(\lib\Util::apiRes(1, 'GET_CONFIG_FAIL'));
        } else
        {
            \Flight::json(\lib\Util::apiRes(0, $data));
        }
    }
    
}

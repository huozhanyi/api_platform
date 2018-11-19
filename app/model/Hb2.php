<?php

/**
 * 我要偷红包模块
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-08-16
 */

namespace mod;

class Hb2
{

    private static $testUserId = 8;         //默认为空，测试用的userid，有则鉴权直接返回该userid
    private static $hbSpaceTime = 1800;         //土豪红包间隔时间30分钟
    private static $hbSpaceTimeFriend = 1800;   //好友红包间隔时间30分钟
    protected static $dataNum = 15;             //每次读取15条数据

    /**
     * 检查用户登录状态
     * @return array
     */

    public static function checkAuth($param = false)
    {
        if (!empty(self::$testUserId))
        {
            return self::$testUserId;
        }

        if (isset($_GET['token']) && isset($_GET['user_id']) && isset($_GET['time']) && isset($_GET['sign']))
        {
            $token = $_GET['token'];
            $userId = $_GET['user_id'];
            $time = $_GET['time'];
            $sign = $_GET['sign'];
            $secretConf = \lib\Util::getConfig('secret');
            $openid = self::getOpenidById($userId);
            $extData = $param ? self::fliterParam($param) : '';
            //验证token
            if ($token != self::setToken($userId, $openid))
            {
                \Flight::json(\lib\Util::apiRes(1, 'ERROR_TOKEN'));
                exit();
            }
            if (md5($token . $secretConf['thb'] . $_GET['time'] . $extData) == $sign && ($time + 1800) > time())
            {
                return $userId;
            }
        }
        \Flight::json(\lib\Util::apiRes(1, 'MISS_AUTH'));
        exit;
    }

    /**
     * 处理加密数组
     * @param array $param
     * @return string $string
     */
    public static function fliterParam($param)
    {
        //过滤空的字段并拼接字符串
        $newParam = array();
        foreach ($param as $key => $p)
        {
            if ($p !== '') $newParam[$key] = $p;
        }
        //排序
        ksort($newParam);
        $string = implode('', $newParam);
        return $string;
    }

    /**
     * openid换用户id
     * @param string $openid
     * @return int
     */
    public static function getIdByOpenid($openid = '')
    {
        $cache = \lib\Cache::init();
        $cacheData = $cache->get('user_id_' . $openid);
        if ($cacheData)
        {
            return (int) $cacheData;
        }
        $db = \lib\DB::init();
        $rs = $db->get('thb_user', ['user_id'], ['openid' => $openid]);
        if ($rs)
        {
            $cache->set('user_id_' . $openid, $rs['user_id'], 86400);
            $cache->set('user_id_' . $rs['user_id'], $openid, 86400);
        }
        return $rs ? (int) $rs['user_id'] : 0;
    }

    /**
     * 用户id换openid
     * @param int $userId
     * @return int
     */
    public static function getOpenidById($userId)
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'user_id_' . $userId;
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $rs = $db->get('thb_user', ['openid'], ['user_id' => $userId]);
        if ($rs)
        {
            $cache->set($cacheKey, $rs['openid'], 86400 * 30);
        }
        return $rs ? $rs['openid'] : 0;
    }

    /**
     * 获取用户信息
     * @param int $userId
     * @return array
     */
    public static function getUser($userId = 0)
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'user_info_' . $userId;
        $cacheData = $cache->get($cacheKey);

        if ($cacheData)
        {
            return $cacheData;
        }

        $db = \lib\DB::init();
        $rs = $db->get('thb_user', '*', ['user_id' => $userId]);

        if ($rs)
        {
            $cache->set($cacheKey, $rs, 86400 * 30);
        }
        return $rs;
    }

    /**
     * 添加用户信息
     * @param array $data
     * @return int
     */
    public static function addUser($data = [])
    {
        $db = \lib\DB::init();
        $db->insert('thb_user', $data);
        $userId = $db->id();
        return $userId;
    }

    /**
     * 编辑用户
     * @param int $userId
     * @param array $data
     * @return boolean
     */
    public static function editUser($userId = 0, $data = [])
    {
        $db = \lib\DB::init();
        $rs = $db->update('thb_user', $data, ['user_id' => $userId]);
        if ($rs !== false)
        {
            $cache = \lib\Cache::init();
            $cache->delete('user_info_' . $userId);
        }
        return $rs;
    }

    /**
     * 生成token
     * @param int $userId string $openid
     * @return string
     */
    public static function setToken($userId, $openid)
    {
        $token = md5($userId . $openid);
        return $token;
    }

    /**
     * 执行偷红包操作
     * @param int $userId int $type
     * @return array $rdata
     */
    public static function snatchHb($userId, $type)
    {
        $db = \lib\DB::init();

        //开始事务
        $db->query("set autocommit = 0");

        //锁行,防止并发执行
        $userInfo = $db->query("select amount,system,lon,lat,path from thb_user where user_id = {$userId} for update")->fetchAll();

        $preRecord = self::getPreRecord($userId, $type);
        $spaceTime = $type != 0 ? self::$hbSpaceTimeFriend : self::$hbSpaceTime;

        $leaveTime = ((int) $preRecord['itime'] + $spaceTime) - time() > 0 ? ((int) $preRecord['itime'] + $spaceTime) - time() : 0;

        //不要考虑是否确认，到点就重新开抢
        //$confirm = isset($preRecord['confirm']) ? $preRecord['confirm'] : 1;
        //如果未达到抢红包时间,或者还没开抢则返回上一次的记录
        if ($leaveTime > 0)
        {
            $rdata['hb_id'] = $preRecord['hb_id'];
            $rdata['money'] = (float) $preRecord['money'];
            $rdata['itime'] = $preRecord['itime'];
            $rdata['leave_time'] = $leaveTime;

            //符合条件，执行抢红包            
        } else
        {
            //根据特定算法计算出单次抢到的红包金额
            $money = self::getMoney($userInfo[0]['amount'], $userInfo[0]['system']);

            $data = array(
                'user_id' => $userId,
                'money' => $money,
                'day' => date("ymd", time()),
                'itime' => time(),
                'friend_id' => $type,
                'hbtype' => 1, //限时红包
                'lon' => $userInfo[0]['lon'],
                'lat' => $userInfo[0]['lat'],
            );

            //更新用户红包账户金额和数量
            $db = \lib\DB::init();
            $db->insert("thb_record", $data);
            $hbId = $db->id();
            if (!$hbId) return false;

            $rdata['hb_id'] = $hbId;
            $rdata['money'] = (float) $data['money'];
            $rdata['itime'] = $data['itime'];
            $rdata['leave_time'] = $spaceTime;
//            $rdata['lon'] = empty($userInfo[0]['lon']) ? '' : $userInfo[0]['lon'];
//            $rdata['lat'] = empty($userInfo[0]['lat']) ? '' : $userInfo[0]['lat'];
            $rdata['path'] = !empty($userInfo[0]['path']) ? $userInfo[0]['path'] : self::getMyPath($userInfo[0]['system']);
            //结束实务
            $db->query("set autocommit = 1");

            $rdata['type'] = 0; //红包
            $rdata['typesub'] = $data['hbtype']; //限时红包
            //只有为非0则放入队列供推出
            if ($rdata['money'] > 0)
            {
                self::pushWebSocket($userId, $rdata);
            }
        }
        return $rdata;
    }

    /**
     * 福利方式的抢红包，直接入账
     * @param type $userId
     * @param type $type
     */
    public static function snatchWealHb($userId)
    {
        $db = \lib\DB::init();

        //开始事务
        $db->query("set autocommit = 0");

        //锁行,防止并发执行
        $userInfo = $db->query("select amount,system,lon,lat,path from thb_user where user_id = {$userId} for update")->fetchAll();

        //根据特定算法计算出单次抢到的红包金额
        $money = self::getMoney($userInfo[0]['amount'], $userInfo[0]['system']);

        $data = array(
            'user_id' => $userId,
            'money' => $money,
            'day' => date("ymd", time()),
            'itime' => time(),
            'friend_id' => 0,
            'confirm' => 1,
            'hbtype' => 2, //1限时红包 2非限时红包
            'lon' => $userInfo[0]['lon'],
            'lat' => $userInfo[0]['lat'],
        );

        //更新用户红包账户金额和数量
        $db = \lib\DB::init();
        $db->insert("thb_record", $data);
        $hbId = $db->id();
        if (!$hbId) return false;

        $db->update("thb_user", ["amount[+]" => $money, "count[+]" => 1], ["user_id" => $userId]);
        //删除用户信息缓存
        $cache = \lib\Cache::init();
        $cache->delete('user_info_' . $userId);
        $rdata['ret'] = 1;

        $rdata['hb_id'] = $hbId;
        $rdata['money'] = (float) $money;
        $rdata['gc_id'] = 0;
        $rdata['goldcoin'] = 0;
        $rdata['itime'] = $data['itime'];
        $rdata['type'] = 0; //0红包类型 1 金币类型
        $rdata['typesub'] = $data['hbtype']; //0红包下  1限时红包、2非限时红包  
//        $rdata['lon'] = empty($userInfo[0]['lon']) ? '' : $userInfo[0]['lon'];
//        $rdata['lat'] = empty($userInfo[0]['lat']) ? '' : $userInfo[0]['lat'];
        $rdata['path'] = !empty($userInfo[0]['path']) ? $userInfo[0]['path'] : self::getMyPath($userInfo[0]['system']);
        $db->query("set autocommit = 1");

        //删除用户信息缓存
        $cache = \lib\Cache::init();
        $cache->delete('user_info_' . $userId);

        //只有为非0则放入队列供推出
        if ($rdata['money'] > 0)
        {
            self::pushWebSocket($userId, $rdata);
        }

        return $rdata;
    }

    /**
     * 花费金币直接入账
     * @param type $userId 用户id
     * @param type $goldcoin 金币数
     * @param type $type 花费类型
     */
    public static function expenseGd($userId, $goldcoin = 0, $type = 11)
    {
        $db = \lib\DB::init();

        //开始事务
        $db->query("set autocommit = 0");

        //锁行,防止并发执行
        $userInfo = $db->query("select goldcoin,goldcount,system,lon,lat from thb_user where user_id = {$userId} for update")->fetchAll();

        //直接就是入账的
        $data['user_id'] = $userId;
        $data['goldcoin'] = -$goldcoin;
        $data['day'] = date("ymd", time());
        $data['itime'] = time();
        $data['confirm'] = 1;
        $data['gctype'] = $type;
        $data['lon'] = $userInfo[0]['lon'];
        $data['lat'] = $userInfo[0]['lat'];
        //花费金币数为负值,支持为0
        //更新金币数
        $db->update("thb_user", ["goldcoin[+]" => $data['goldcoin'], "goldcount[+]" => 1], ["user_id" => $userId]);

        //增加记录
        $db->insert("thb_record_gold", $data);
        $gcId = $db->id();
        if (!$gcId) return false;

        $db->query("set autocommit = 1");

        //返回
        $rdata['ret'] = 1;
//        $rdata['gc_id'] = $gcId;
        $rdata['goldcoin'] = $userInfo[0]['goldcoin'] + $data['goldcoin']; //剩下的金币数
        $rdata['itime'] = time();
        $rdata['type'] = 1; //0红包类型 1金币类型
        //删除用户信息缓存
        $cache = \lib\Cache::init();
        $cache->delete('user_info_' . $userId);

        return $rdata;
    }

    /**
     * 执行领金币操作
     * @param int $userId int $type
     * @return array $rdata
     */
    public static function gainGold($userId, $type, $friendId = 0)
    {
        $rdata = null;
        $nowday = date('ymd');

        $db = \lib\DB::init();

        //开始事务
        $db->query("set autocommit = 0");

        //邀请好友的情况，发一个邀请链接包含邀请人uid，邀请的新用户登录后，通过附带的邀请人uid来给邀请人发金币
        if (!empty($friendId) && $type = 3)
        {
            //操作的是好友
            //锁行,防止并发执行
            $userInfo = $db->query("select goldcoin,system,lon,lat,path from thb_user where user_id = {$friendId} for update")->fetchAll();
            //这时候取的是邀请人的信息，被邀请人为邀请人的好友
            $data = array(
                'user_id' => $friendId,
                'day' => date("ymd", time()),
                'itime' => time(),
                'friend_id' => $userId,
                'lon' => $userInfo[0]['lon'],
                'lat' => $userInfo[0]['lat'],
            );
        } else
        {
            //操作的是本人
            //锁行,防止并发执行
            $userInfo = $db->query("select goldcoin,system,lon,lat,path from thb_user where user_id = {$userId} for update")->fetchAll();

            $data = array(
                'user_id' => $userId,
                'day' => date("ymd", time()),
                'itime' => time(),
                'friend_id' => $friendId,
                'lon' => $userInfo[0]['lon'],
                'lat' => $userInfo[0]['lat'],
            );
        }

        switch ($type)
        {
            case 1: //1 直接领
                //直接就是入账的
                $data['confirm'] = 1;
                $data['gctype'] = $type;

                //得到金币数
                $data['goldcoin'] = self::getGoldCoin($userInfo[0]['goldcoin'], $userInfo[0]['system'], $type);

                //更新金币数
                $db->update("thb_user", ["goldcoin[+]" => $data['goldcoin'], "goldcount[+]" => 1], ["user_id" => $userId]);

                //增加记录
                $db->insert("thb_record_gold", $data);
                $gcId = $db->id();
                if (!$gcId) return false;
                //返回
                $rdata['ret'] = 1; //抢到已入账，决定是否入推送队列
                $rdata['hb_id'] = 0;
                $rdata['money'] = 0;
                $rdata['gc_id'] = $gcId;
                $rdata['goldcoin'] = (int) $data['goldcoin'];
                $rdata['itime'] = $data['itime'];
                $rdata['type'] = 1; //0红包类型 1金币类型
                break;

            case 2://2 签到领，根据判断上一次领取记录是否是今天
                //上次签到记录
                $preRecordGold = self::getPreRecordGold($userId, $type, $friendId);
                //按今天日期来比对
                if (date('ymd', (int) $preRecordGold['itime']) == $nowday)
                {
                    //若最近记录是今日，则已领过，返回上次记录
                    $rdata['gc_id'] = $preRecordGold['gc_id'];
                    $rdata['goldcoin'] = (int) $preRecordGold['goldcoin'];
                    $rdata['itime'] = $preRecordGold['itime'];
                    $rdata['nowdate'] = date('ymd', (int) $preRecordGold['itime']);
                    $rdata['ret'] = 0; //未领到
                    $data['confirm'] = 0;
                } else
                {
                    //今日未领过
                    $data['confirm'] = 1;
                    $data['gctype'] = $type;

                    //得到金币数
                    $data['goldcoin'] = self::getGoldCoin($userInfo[0]['goldcoin'], $userInfo[0]['system'], $type);

                    //更新金币数
                    $db->update("thb_user", ["goldcoin[+]" => $data['goldcoin'], "goldcount[+]" => 1], ["user_id" => $userId]);

                    //增加记录
                    $db->insert("thb_record_gold", $data);
                    $gcId = $db->id();
                    if (!$gcId) return false;

                    //组合返回值
                    $rdata['gc_id'] = $gcId;
                    $rdata['goldcoin'] = (int) $data['goldcoin'];
                    $rdata['itime'] = $data['itime'];
                    $rdata['nowdate'] = $nowday;
                    $rdata['ret'] = 1; //领到已入账
                }
                break;

            case 3: //3 邀请领金币
                $data['confirm'] = 1;
                $data['gctype'] = $type;

                //得到金币数
                $data['goldcoin'] = self::getGoldCoin($userInfo[0]['goldcoin'], $userInfo[0]['system'], $type);

                //更新邀请人的金币数
                $db->update("thb_user", ["goldcoin[+]" => $data['goldcoin'], "goldcount[+]" => 1], ["user_id" => $friendId]);

                //相互加好友操作在新用户登录时候已执行
                //增加邀请人的领金币记录，被邀请人为好友
                $db->insert("thb_record_gold", $data);
                $gcId = $db->id();
                if (!$gcId) return false;

                //返回
                $rdata = (int) $data['goldcoin'];
                break;

            default:
                break;
        }

        $db->query("set autocommit = 1");

        //确认后删除用户信息缓存
        if (!empty($data['confirm']) && $data['confirm'] == 1)
        {
            $cache = \lib\Cache::init();
            $cache->delete('user_info_' . $userId);
        }

        //红包和金币下的子分类
        $rdata['type'] = 1; //0红包类型 1 金币类型
        $rdata['typesub'] = $type;
//        $rdata['lon'] = empty($userInfo[0]['lon']) ? '' : $userInfo[0]['lon'];
//        $rdata['lat'] = empty($userInfo[0]['lat']) ? '' : $userInfo[0]['lat'];
        $rdata['path'] = !empty($userInfo[0]['path']) ? $userInfo[0]['path'] : self::getMyPath($userInfo[0]['system']);

        //放入队列供推出
        if ($rdata['ret'])
        {
            self::pushWebSocket($userId, $rdata);
        }

        return $rdata;
    }

    /*
     * 确认红包并入库
     * @param int $userId int $hbId
     * @return float $money
     */

    public static function confirmHb($userId, $hbId)
    {
        $db = \lib\DB::init();

        $rdata = false;

        //开始事务
        $db->query("set autocommit = 0");

        //锁行,防止并发执行
        $userInfo = $db->query("select user_id from thb_user where user_id = {$userId} for update")->fetchAll();

        $result = $db->get("thb_record", ["money"], ["user_id" => $userId, "hb_id" => $hbId, "confirm" => 0]);
        if ($result)
        {
            $db->update("thb_record", ["confirm" => 1], ["user_id" => $userId, "hb_id" => $hbId]);
            $db->update("thb_user", ["amount[+]" => $result['money'], "count[+]" => 1], ["user_id" => $userId]);
            //删除用户信息缓存
            $cache = \lib\Cache::init();
            $cache->delete('user_info_' . $userId);
            $rdata['ret'] = 1;
        }
        //结束事务
        $db->query("set autocommit = 1");
        return $rdata;
    }

    /**
     * 根据特定算法计算出单次抢到的红包金额
     * @param float $amount int $system
     * @return float $money
     */
    private static function getMoney($amount, $system = 1)
    {
        if ($system == 2)
        {
            $version = isset($_GET['version']) ? $_GET['version'] : '';
            $verData = self::getVersion($system);

            //设备为IOS并且为审核状态的只能抢小额红包(审核状态的红包不能为大额红包，麻囵烦)
            if ($version == $verData['ver'])
            {
                $money = round(mt_rand(1, 100) / 100, 2);
                return $money;
            }
        }

        //用户总金额大于500的话，用户有1/10的几率抢到0元红包
        if ($amount >= 500)
        {
            $zero = mt_rand(0, 6);
            if ($zero == 0) return 0;
        }

        //白名单用户可以抢到1000并提现
        if (isset($_GET['user_id']) && $_GET['user_id'] == 1)
        {
            if ($amount < 500)
            {
                $money = round(mt_rand(500, 1000) / 100, 2);
            } elseif (500 <= $amount && $amount < 700)
            {
                $money = round(mt_rand(1, 300) / 100, 2);
            } elseif (700 <= $amount && $amount < 960)
            {
                $money = round(mt_rand(1, 10) / 100, 2);
            } else
            {
                //有三分之一机会抢不到
                //$zero = mt_rand(0,2);
                //if($zero == 0)  return 0;
                $money = round(mt_rand(1, 9) / 100, 2);
            }
            //普通用户不能抢到1000也不能提现
        } else
        {
            if ($amount < 500)
            {
                $money = round(mt_rand(500, 1000) / 100, 2);
            } elseif (500 <= $amount && $amount < 700)
            {
                $money = round(mt_rand(1, 300) / 100, 2);
            } elseif (700 <= $amount && $amount < 800)
            {
                $money = round(mt_rand(1, 20) / 100, 2);
            } elseif (800 <= $amount && $amount < 960)
            {
                $money = 0.01;
            } else
            {
                $money = 0;
            }
        }
        return $money;
    }

    /**
     * 计算可以领取金币的数量返回
     * @param type $goldcoin 已有金币数
     * @param type $system 系统
     * @param type $type 领取类型
     * @return type
     */
    private static function getGoldCoin($goldcoin, $system = 1, $type = 1)
    {

        switch ($type)
        {
            //1 直接领随机
            case 1:
                $num = rand(1, 10);

                break;

            //2 签到领是固定数额
            case 2:
                $num = 10;

                break;

            default://3 邀请领，给邀请人增加金币

                $num = 10; //测试

                break;
        }
        return $num;
    }

    /**
     * 获取上一次的红包记录
     * @param int $userId int $type
     * @return 
     */
    private static function getPreRecord($userId, $type)
    {
        /*
          $cache = \lib\Cache::init();
          $cacheKey = 'thb_prerecord_'.$userId.'_'.$type;
          $cacheData = $cache->get($cacheKey);
          if ( $cacheData )
          {
          return $cacheData;
          }
         */
        $db = \lib\DB::init();
//        $result = $db->get("thb_record", "*", ["user_id" => $userId, "friend_id" => $type, "ORDER" => ["hb_id" => "DESC"]]);
        //这里只取最新的限时红包记录，即hbtype=1的情况
        $result = $db->get("thb_record", "*", ["user_id" => $userId, "friend_id" => $type, "hbtype" => 1, "ORDER" => ["hb_id" => "DESC"]]);
        if ($result)
        {
            // $cache->set($cacheKey,$result,1800);
            return $result;
        } else
        {
            return false;
        }
    }

    /**
     * 获取上一次的金币记录
     * @param int $userId int $type
     * @return 
     */
    public static function getPreRecordGold($userId, $type, $friendId = 0)
    {
        /*
          $cache = \lib\Cache::init();
          $cacheKey = 'thb_prerecord_'.$userId.'_'.$type;
          $cacheData = $cache->get($cacheKey);
          if ( $cacheData )
          {
          return $cacheData;
          }
         */
        $db = \lib\DB::init();
        $result = $db->get("thb_record_gold", "*", ["user_id" => $userId, "friend_id" => $friendId, "gctype" => $type, "ORDER" => ["gc_id" => "DESC"]]);
        if ($result)
        {
            // $cache->set($cacheKey,$result,1800);
            return $result;
        } else
        {
            return false;
        }
    }

    /**
     * 获取倒计时时间
     * @param int $userId int $type
     * @return int
     */
    private static function getLeaveTime($userId, $type)
    {
        $preRecord = self::getPreRecord($userId, $type);
        $spaceTime = $type != 0 ? self::$hbSpaceTimeFriend : self::$hbSpaceTime;
        return ((int) $preRecord['itime'] + $spaceTime) - time() > 0 ? ((int) $preRecord['itime'] + $spaceTime) - time() : 0;
    }

    /**
     * 获取指定用户红包记录
     * @param int $userId int $page
     * @return 
     */
    public static function getRecord($userId, $page)
    {
        $dataNum = self::$dataNum;
        $offset = $page * $dataNum;
        $db = \lib\DB::init();
        $result = $db->select("thb_record", ["hb_id", "money", "itime", "friend_id", "hbtype", "lon", "lat"], ["user_id" => $userId, "confirm" => 1, "ORDER" => ["hb_id" => "DESC"], "LIMIT" => [$offset, $dataNum]]);
        if (!$result) $result = $db->select("thb_record_old", ["hb_id", "money", "itime", "friend_id", "hbtype", "lon", "lat"], ["user_id" => $userId, "confirm" => 1, "ORDER" => ["hb_id" => "DESC"], "LIMIT" => [$offset, $dataNum]]);

        if ($result)
        {
            $data = array();
            $userInfo = self::getUser($userId);
            foreach ($result as $key => $res)
            {
                if ($res['friend_id'] == 0)
                {
                    $nickname = $userInfo['nickname'];
                    $headimgurl = $userInfo['headimgurl'];
                    //广告红包
                } elseif ($res['friend_id'] < 0)
                {
                    $fixedHbInfo = self::fixedHb();
                    $nickname = $fixedHbInfo[$res['friend_id']]['nickname'];
                    $headimgurl = $fixedHbInfo[$res['friend_id']]['headimgurl'];
                } else
                {
                    $friendInfo = self::getUser($res['friend_id']);
                    $nickname = isset($friendInfo['nickname']) ? $friendInfo['nickname'] : '';
                    $headimgurl = isset($friendInfo['headimgurl']) ? $friendInfo['headimgurl'] : '';
                }
                $data[$key]['nickname'] = $nickname;
                $data[$key]['headimgurl'] = $headimgurl;
                $data[$key]['hb_id'] = $res['hb_id'];
                $data[$key]['money'] = $res['money'];
                $data[$key]['itime'] = $res['itime'];
                $data[$key]['hbtype'] = $res['hbtype']; //类型
                $data[$key]['lon'] = empty($res['lon']) ? '' : $res['lon']; //经度
                $data[$key]['lat'] = empty($res['lat']) ? '' : $res['lat']; //纬度
            }
            return $data;
        } else
        {
            return false;
        }
    }

    /**
     * 获取广告列表
     */
    public static function getAdslist()
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'thb_ads';
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }

        $domain_url = 'http://51qhb.16999.com';
        $db = \lib\DB::init();
        $result = $db->select("thb_ads", "*");
//        $result = $db->select("thb_ads", "*", ['status' => 1,"ORDER" => ["id" => "DESC"]]);
        if ($result)
        {
//            $data = array();
            foreach ($result as $key => $res)
            {
                if (!empty($res['logo']) && strpos($res['logo'], 'http') !== 0)
                {
                    $result[$key]['logo'] = $domain_url . $res['logo'];
                }
                if (!empty($res['img']) && strpos($res['img'], 'http') !== 0)
                {
                    $result[$key]['img'] = $domain_url . $res['img'];
                }
                if (!empty($res['pic']) && strpos($res['pic'], 'http') !== 0)
                {
                    $result[$key]['pic'] = $domain_url . $res['pic'];
                }
                if (!empty($res['banner']) && strpos($res['banner'], 'http') !== 0)
                {
                    $result[$key]['banner'] = $domain_url . $res['banner'];
                }

//                unset($result[$key]['status']);
                unset($result[$key]['uptime']);
                unset($result[$key]['download_num']);
            }
            $cache->set($cacheKey, $result, 30);
//            return $data;
            return $result;
        } else
        {
            return FALSE;
        }
        exit;
    }

    /**
     * 获取指定用户金币记录
     * @param int $userId int $page
     * @return 
     */
    public static function getRecordGold($userId, $page)
    {
        $dataNum = self::$dataNum;
        $offset = $page * $dataNum;
        $db = \lib\DB::init();
        $result = $db->select("thb_record_gold", ["gc_id", "goldcoin", "itime", "friend_id", "gctype", "lon", "lat"], ["user_id" => $userId, "confirm" => 1, "ORDER" => ["gc_id" => "DESC"], "LIMIT" => [$offset, $dataNum]]);
        if ($result)
        {
            $data = array();
            $userInfo = self::getUser($userId);
            foreach ($result as $key => $res)
            {
                if ($res['friend_id'] == 0)
                {
                    $nickname = $userInfo['nickname'];
                    $headimgurl = $userInfo['headimgurl'];
                } else
                {
                    $friendInfo = self::getUser($res['friend_id']);
                    $nickname = isset($friendInfo['nickname']) ? $friendInfo['nickname'] : '';
                    $headimgurl = isset($friendInfo['headimgurl']) ? $friendInfo['headimgurl'] : '';
                }
                $data[$key]['nickname'] = $nickname;
                $data[$key]['headimgurl'] = $headimgurl;
                $data[$key]['gc_id'] = $res['gc_id'];
                $data[$key]['goldcoin'] = $res['goldcoin'];
                $data[$key]['itime'] = $res['itime'];
                $data[$key]['gctype'] = $res['gctype']; //类型
                $data[$key]['friend_id'] = $res['friend_id']; //被邀请人id
                $data[$key]['lon'] = empty($res['lon']) ? '' : $res['lon']; //经度
                $data[$key]['lat'] = empty($res['lat']) ? '' : $res['lat']; //纬度
            }
            return $data;
        } else
        {
            return false;
        }
    }

    /**
     * 全部用户金币记录
     * @param int $userId int $page
     * @return 
     */
    public static function getAllRecordGoldHb($page = 0)
    {
//        $dataNum = self::$dataNum;
        $dataNum = 25;
        $offset = $page * $dataNum;

        $cache = \lib\Cache::init();
        $cacheKey = 'thb_allrecordgold_' . $dataNum . $offset;
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }

        $data = array();

        $db = \lib\DB::init();

        $result_gd = $db->select("thb_record_gold", ["gc_id", "user_id", "goldcoin", "itime", "friend_id", "gctype"], ["confirm" => 1, "ORDER" => ["gc_id" => "DESC"], "LIMIT" => [$offset, $dataNum]]);
        if ($result_gd)
        {

            foreach ($result_gd as $key => $res)
            {
                $key = $res['itime'] . 'gd';
                $data[$key]['type'] = 1; //记录类型，0红包1金币
                $data[$key]['typesub'] = $res['gctype']; //类型
                $data[$key]['money'] = 0; //红包数
                $data[$key]['goldcoin'] = $res['goldcoin'];
                $data[$key]['itime'] = $res['itime'];

                $friendInfo = self::getUser($res['user_id']);
//                $data[$key]['user_id'] = isset($friendInfo['user_id']) ? $friendInfo['user_id'] : '';
                $data[$key]['path'] = isset($friendInfo['path']) ? $friendInfo['path'] : self::getMyPath($friendInfo['system']);
                $data[$key]['nickname'] = isset($friendInfo['nickname']) ? $friendInfo['nickname'] : '';
                $data[$key]['headimgurl'] = isset($friendInfo['headimgurl']) ? $friendInfo['headimgurl'] : '';
                $data[$key]['friend_id'] = $res['friend_id']; //被邀请人id
//                $data[$key]['lon'] = empty($res['lon']) ? '' : $res['lon']; //经度
//                $data[$key]['lat'] = empty($res['lat']) ? '' : $res['lat']; //纬度
            }
        }

        $result_hb = $db->select("thb_record", ["hb_id", "user_id", "money", "itime", "friend_id", "hbtype"], ["money[>]" => 0.1, "confirm" => 1, "ORDER" => ["hb_id" => "DESC"], "LIMIT" => [$offset, $dataNum]]);
        if ($result_hb)
        {
            foreach ($result_hb as $key => $res)
            {
                $key = $res['itime'] . 'hb';
                $data[$key]['type'] = 0; //记录类型，0红包1金币
                $data[$key]['typesub'] = $res['hbtype']; //类型
                $data[$key]['money'] = $res['money']; //红包金额
                $data[$key]['goldcoin'] = 0;
                $data[$key]['itime'] = $res['itime'];

                $friendInfo = self::getUser($res['user_id']);
//                $data[$key]['user_id'] = isset($friendInfo['user_id']) ? $friendInfo['user_id'] : '';
                $data[$key]['path'] = isset($friendInfo['path']) ? $friendInfo['path'] : self::getMyPath($friendInfo['system']);
                $data[$key]['nickname'] = isset($friendInfo['nickname']) ? $friendInfo['nickname'] : '';
                $data[$key]['headimgurl'] = isset($friendInfo['headimgurl']) ? $friendInfo['headimgurl'] : '';
                $data[$key]['friend_id'] = $res['friend_id']; //被邀请人id
//                $data[$key]['lon'] = empty($res['lon']) ? '' : $res['lon']; //经度
//                $data[$key]['lat'] = empty($res['lat']) ? '' : $res['lat']; //纬度
            }
        }

        if (!empty($data))
        {
            krsort($data);

            $data = array_values($data);

            $cache->set($cacheKey, $data, 180);
        }

        return $data;
    }

    /**
     * 关联好友关系
     * @param int $friendId int $userId
     * @return
     */
    public static function relateFriends($friendId, $userId)
    {
        if ($friendId == $userId) return false;
        $db = \lib\DB::init();
        $cache = \lib\Cache::init();
        $friendsData = $db->get("thb_user", ["friends"], ["user_id" => $friendId]);
        //如果不存在该好友，则不作任何操作
        if (!$friendsData) return false;
        $friendArr = (array) json_decode($friendsData['friends'], true);
        if (!in_array($userId, $friendArr))
        {
            array_push($friendArr, $userId);
            $friends = json_encode($friendArr);
            $db->update("thb_user", ["friends" => $friends, "friendsnum[+]" => 1], ["user_id" => $friendId]);
            $cache->delete('user_info_' . $friendId);
        }

        $friendsData = $db->get("thb_user", ["friends"], ["user_id" => $userId]);
        $friendArr = (array) json_decode($friendsData['friends'], true);
        if (!in_array($friendId, $friendArr))
        {
            array_push($friendArr, $friendId);
            $friends = json_encode($friendArr);
            $db->update("thb_user", ["friends" => $friends, "friendsnum[+]" => 1], ["user_id" => $userId]);
            $cache->delete('user_info_' . $userId);
        }
        return true;
    }

    /**
     * 获取官方红包
     * @param int $userId
     * @return array
     */
    public static function getOfficial($userId)
    {
        $userInfo = self::getUser($userId);
        $data['friend_id'] = 0;
        $data['nickname'] = $userInfo['nickname'];
        $data['headimgurl'] = $userInfo['headimgurl'];
        $data['leave_time'] = self::getLeaveTime($userId, 0);
        $data['count'] = self::getHbCount();
        return $data;
    }

    /**
     * 获取好友红包
     * @param int $userId
     * @return 
     */
    public static function getFriends($userId)
    {
        $userInfo = self::getUser($userId);
        $friends = json_decode($userInfo['friends'], true);
        $fixedData = self::fixedHb();

        //只获取6个好友
        $friends = $friends ? array_slice($friends, 0, 10) : array();
//        $arrayList = array_merge($friends, $fixedData);
        $arrayList = array_merge($fixedData, $friends); //固定放前面
        foreach ($arrayList as $key => $list)
        {
            if (!isset($list['friend_id'])) //这里$list 是array_merge的好友id，非默认广告好友
            {
                $friendUserInfo = self::getUser($list);
                $data[$key]['friend_id'] = $list;
                $data[$key]['nickname'] = $friendUserInfo['nickname'];
                $data[$key]['headimgurl'] = $friendUserInfo['headimgurl'];
                $data[$key]['goldcoin'] = empty($friendUserInfo['goldcoin']) ? 0 : $friendUserInfo['goldcoin'];
            } else
            {
                $data[$key] = $list;
            }
            $data[$key]['leave_time'] = self::getLeaveTime($userId, $data[$key]['friend_id']);
        }
        return $data;
    }

    /**
     * 固定的好友红包(天猫，淘宝，京东）
     * @param 
     * @return array
     */
    private static function fixedHb()
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'thb_fixed_hb';
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $result = $db->select('thb_ad', ["type", "name", "imgurl"]);
        if ($result)
        {
            $data = array();
            foreach ($result as $key => $res)
            {
                $data[$res['type']]['friend_id'] = (int) $res['type'];
                $data[$res['type']]['nickname'] = $res['name'];
                $data[$res['type']]['headimgurl'] = $res['imgurl'];

                switch (abs($res['type']))
                {
                    case 1:
                        $goldcoin = 6666;
                        break;
                    case 2:
                        $goldcoin = 7777;
                        break;
                    case 3:
                        $goldcoin = 8888;
                        break;
                    default:
                        $goldcoin = 9999;
                        break;
                }

                $data[$res['type']]['goldcoin'] = $goldcoin;
            }
            $cache->set($cacheKey, $data, 86400 * 30);
        }
        return $data;
    }

    /*
     * 获取总的红包个数
     */

    public static function getHbCount()
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'hb_count';
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $data = $db->sum("thb_user", "count", ["count[>]" => 0]);
        if ($data)
        {
            $data += 6000000;
            $cache->set($cacheKey, $data, 3600);
        }
        return $data;
    }

    /**
     * 获取软件版本号
     * @param string $system 
     * @return array
     */
    public static function getVersion($system)
    {
        $db = \lib\DB::init();
        $cache = \lib\Cache::init();
        $cacheKey = 'thb_version_' . $system;
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }
        $data = $db->get('thb_version', ["ver", "changlog", 'md5', 'link', 'force'], ['id' => $system]);
        if ($data)
        {
            $cache->set($cacheKey, $data, 86400);
        }
        return $data;
    }

    /*
     * 提现
     * @param int $userId array $data
     * @return boolen|array
     */

    public static function deposit($userId, $data)
    {
        $db = \lib\DB::init();

        //开始事务
        $db->query("set autocommit = 0");

        //锁行,防止并发执行
        $userInfo = $db->query("select amount from thb_user where user_id = {$userId} for update")->fetchAll();

        $amount = $userInfo[0]['amount'];
        if ($amount < $data['money'])
        {
            return false;
        }

        $data['itime'] = time();
        $data['user_id'] = $userId;

        //验证码不用加入数据库
        unset($data['vercode']);
        $db->insert("thb_deposit", $data);
        $id = $db->id();
        if ($id)
        {
            $db->update("thb_user", ["amount[-]" => $data['money']], ["user_id" => $userId]);

            //结束实务
            $db->query("set autocommit = 1");

            //删除用户信息缓存
            $cache = \lib\Cache::init();
            $cache->delete('user_info_' . $userId);

            $rdata['ret'] = 1;
            return $rdata;
        } else
        {
            return false;
        }
    }

    /*
     * 获取验证码
     * @param int $tpl_id 模板id string $tpl_value 模板内容（#code#=123456）
     * @return array|boolen
     * @remark 模板 51541 【小陀螺】您的验证码是#code#，10分钟内有效，祝您生活愉快！
     */

    public static function getVerCode($mobile = 0, $tpl_id = 0, $tpl_value = '')
    {
        if (!$mobile)
        {
            return false;
        }
        $config = \lib\Util::getConfig('juhe');

        $curl = new \Curl\Curl();
        $curl->get('http://v.juhe.cn/sms/send', array(
            'mobile' => $mobile,
            'tpl_id' => $tpl_id,
            'tpl_value' => urlencode($tpl_value),
            'key' => $config['ver_code_key']
        ));
        if ($curl->error)
        {
            return false;
        } else
        {
            $curl_data = $curl->response;
        }
        return json_decode($curl_data, true);
    }

    /**
     * 我的提现记录
     * @param int $userId int $page
     * @return array
     */
    public static function getDepositList($userId, $page)
    {
        $dataNum = self::$dataNum;
        $offset = ($page - 1) * $dataNum;
        $db = \lib\DB::init();

        $data = $db->select("thb_deposit", ["deposit_id", "money", "itime", "status", "path"], ["user_id" => $userId, "ORDER" => ["deposit_id" => "DESC"], "LIMIT" => [$offset, $dataNum]]);

        if ($data)
        {
            return $data;
        } else
        {
            return false;
        }
    }

    /**
     * 获取全部提现记录
     * @param int $page
     * @return array
     */
    public static function getAllDepositList()
    {
        //机器人提现数据
        $robotData = '[
        {
            "deposit_id": "-1",
            "money": "1020.2",
            "itime":"1510790643",
            "nickname": "错过的情人",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/C59E0C56A4962D93BC19D8FADDD3A609/100",
            "path":"安徽滁州南谯区"
        },
        {
            "deposit_id": "-1",
            "money": "1000",
            "itime":"1510774983",
            "nickname": "@再会了、",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/C22E19A3E7E44C9A6C6FA2E072C42E71/100",
            "path":"福建龙岩新罗区"
        },
        {
            "deposit_id": "-1",
            "money": "1200.80",
            "itime":"1510756352",
            "nickname": "宝宝จุ๊บ คิดถึง",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/F31105DC6392507F519E8E262BB33C82/100",
            "path":"福建南平光泽县"
        },
        {
            "deposit_id": "-1",
            "money": "1111.9",
            "itime":"1510665758",
            "nickname": "终是1，场梦",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/D7081C992802A63D731540245F85BFE7/100",
            "path":"甘肃酒泉玉门市"
        },
        {
            "deposit_id": "-1",
            "money": "1002.12",
            "itime":"1510493602",
            "nickname": "神影",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/B3CF923A01CFFAF9E0B52F647067229B/100",
            "path":"广东惠州惠城区"
        },
        {
            "deposit_id": "-1",
            "money": "1005.39",
            "itime":"1510329782",
            "nickname": "小段家电商行",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/53AD58A692A24725419337174AFC763B/100",
            "path":"广东江门新会区"
        },
        {
            "deposit_id": "-1",
            "money": "1010.22",
            "itime":"1510198322",
            "nickname": "彬℡少",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/912B507F761AFF6A91BA7827CC660220/100",
            "path":"广东梅州梅县"
        },
        {
            "deposit_id": "-1",
            "money": "1500.40",
            "itime":"1509930594",
            "nickname": "尐吖頭脾氣拽",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/E64EA24E6A56B141B3259E802EB2A501/100",
            "path":"广西南宁邕宁区"
        },
        {
            "deposit_id": "-1",
            "money": "1221.12",
            "itime":"1509519569",
            "nickname": "Loser丶",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/362CAEC5B775DD91BB47FBFA93AFA3C1/100",
            "path":"广西桂林荔浦县"
        },
        {
            "deposit_id": "-1",
            "money": "1000.1",
            "itime":"1509278915",
            "nickname": "AAA新成",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/E3022E6424C64F7F85146DBA5EE10990/100",
            "path":"贵州贵阳息烽县"
        },
        {
            "deposit_id": "-1",
            "money": "1000.9",
            "itime":"1509216995",
            "nickname": "十里七华乡 妮",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/9BED4FD80205F33F444157ED70445F27/100",
            "path":"海南海口龙华区"
        },
        {
            "deposit_id": "-1",
            "money": "1000.12",
            "itime":"1508955981",
            "nickname": "幸福",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/EF84569B475F1FBB2C561696371987A0/100",
            "path":"河北石家庄新华区"
        },
        {
            "deposit_id": "-1",
            "money": "1000.4",
            "itime":"1508945981",
            "nickname": "心想事成",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/FE21884A58345EDE5B3E4775376808F6/100",
            "path":"河北保定容城县"
        },
        {
            "deposit_id": "-1",
            "money": "1000.92",
            "itime":"1508915909",
            "nickname": "黄浩昭",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/9C35717F801B71E886EAFF9505806A21/100",
            "path":"河南洛阳孟津县"
        },
        {
            "deposit_id": "-1",
            "money": "1000.24",
            "itime":"1508825909",
            "nickname": "爱的等候……",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/E6B7B14AC4ED0EE5E4A04F025AB00057/100",
            "path":"河北衡水深州市"
        },
        {
            "deposit_id": "-1",
            "money": "1090.21",
            "itime":"1508225009",
            "nickname": "David Yang",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/DCDE4A472743399FD0C7F62F0AA4FA91/100",
            "path":"湖南永州新田县"
        },
        {
            "deposit_id": "-1",
            "money": "1020.27",
            "itime":"1508005009",
            "nickname": "都市新感觉",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/F4867BA8E1EC7ADF8F509E4B40F36C85/100",
            "path":"吉林长春德惠市"
        },
        {
            "deposit_id": "-1",
            "money": "1010.21",
            "itime":"1507000000",
            "nickname": "♛重复★犯错♚",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/1373C11FC9310534BCEC77056A62B1AF/100",
            "path":"吉林辽源东丰县"
        },
        {
            "deposit_id": "-1",
            "money": "1000.3",
            "itime":"1506400900",
            "nickname": "如果没有明天",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/9314AC9C26BC89A95FE29EF360D081AB/100",
            "path":"江西抚州广昌县"
        },
        {
            "deposit_id": "-1",
            "money": "1000.9",
            "itime":"1506100309",
            "nickname": "郭刚",
            "headimgurl": "http://q.qlogo.cn/qqapp/1104225752/4A7D991A44A4907D7E9E5794DE516E03/100",
            "path":"新疆和田民丰县"
        }
    ]';

        $db = \lib\DB::init();
        $data = $db->select("thb_deposit", ["user_id", "deposit_id", "money", "itime", "path"], ["STATUS" => 1, "ORDER" => ["deposit_id" => "DESC"], "LIMIT" => 90]);
        if ($data)
        {
            foreach ($data as $key => $res)
            {
                $resData[$key]['deposit_id'] = $res['deposit_id'];
                $resData[$key]['money'] = $res['money'];
                $resData[$key]['itime'] = $res['itime'];
                //获取用户信息
                $userInfo = self::getUser($res['user_id']);
                $resData[$key]['path'] = !empty($res['path']) ? $res['path'] : self::getMyPath($userInfo['system']);
                $resData[$key]['nickname'] = $userInfo['nickname'];
                $resData[$key]['headimgurl'] = $userInfo['headimgurl'];
            }
            $robotData = json_decode($robotData, true);
            $resData = array_merge($resData, $robotData);
            //将图片替换
            if ($resData)
            {
                $count_img = count($resData);
                //从game表取得对应数据量的图片
                $game_user_img = self::gerGameUser($count_img);

                foreach ($resData as $key => $res)
                {
                    $resData[$key]['headimgurl'] = $game_user_img[$key]['headimgurl'];
                }
            }

            array_multisort(array_column($resData, 'itime'), SORT_DESC, $resData);
            return $resData;
        } else
        {
            return false;
        }
    }

    /**
     * 得到游戏用户的图片，取隔壁堆堆乐的数据库的用户表图片内容
     */
    private static function gerGameUser($count_img)
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'game_user' . $count_img;
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }
        $dbConfig = \lib\Util::getConfig('db');
        $dbConfig['database_name'] = 'game_16999_com';
        $dbConfig['username'] = 'gameyljjj';
        $dbConfig['password'] = '7E2ptxyT88hW7k78';

        $db = new \Medoo\Medoo($dbConfig);
        $data = $db->select("game_user", ["headimgurl"], ["ORDER" => ["user_id" => "DESC"], "LIMIT" => [rand(1, 100), $count_img]]);

        //缓存1天
        $cache->set($cacheKey, $data, 86400);
        return $data;
    }

    /**
     * 获取最近抢到的红包记录
     * @param int $limit
     * @return array
     */
    public static function getRecently($limit = 10)
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'thb_recently';
        $cacheData = $cache->get($cacheKey);
        if ($cacheData)
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $result = $db->select("thb_record", ["hb_id", "user_id", "money", "itime", "hbtype", "lon", "lat"], ["confirm" => 1, "money[>]" => 0.1, "ORDER" => ["hb_id" => "DESC",], "LIMIT" => $limit]);
        foreach ($result as $key => $res)
        {
            $userInfo = self::getUser($res['user_id']);
            $data[$key]['hb_id'] = $res['hb_id'];
            $data[$key]['money'] = $res['money'];
            $data[$key]['itime'] = $res['itime'];
            $data[$key]['nickname'] = $userInfo['nickname'];
            $data[$key]['hbtype'] = $userInfo['hbtype']; //类型
            $data[$key]['lon'] = $userInfo['lon']; //经度
            $data[$key]['lat'] = $userInfo['lat']; //纬度
        }
        if ($data)
        {
            //缓存3分钟
            $cache->set($cacheKey, $data, 180);
            return $data;
        } else
        {
            return false;
        }
    }

    /**
     * 获取应用配置
     * 
     * @param int $key 键值
     * @return array|false 
     */
    public static function getConfig($key)
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'thb_config_' . $key;
        $cacheData = $cache->get($cacheKey);
        if ($cacheData) return $cacheData;

        $db = \lib\DB::init();
        $configData = $db->get('thb_config', array('value'), array('name' => $key));
        if (!empty($configData['value']))
        {
            $redata = json_decode($configData['value'], true);
            $cache->set($cacheKey, $redata, 86400 * 30);
            return $redata;
        } else
        {
            return false;
        }
    }

    //推给需要websocket
    public static function pushWebSocket($user_id, $recode_data)
    {
        $config_websocket = \lib\Util::getConfig('websocket');
        //开关
        if (!$config_websocket['on'])
        {
            return '';
        }
        
        
        $config = \lib\Util::getConfig('cache');
        $config['redis']['persistent'] = 1;
        $redis = new \Predis\Client($config['redis'], ['prefix' => $config['cache_prefix']]);
        
        $msglen = $redis->llen($config_websocket['key_all_weal_list']);
        
        //保证队列数量不会太大，保留4小时的数据，数据量少的时间段可缓冲
        if($msglen >= 14400)
        {
            return '';
        }
        
        
        if (!isset($recode_data['type']))
        {
            //若金币为0，则表示是红包，则type=0,因为红包可能为0所以只能用金币判断
            $recode_data['type'] = empty($recode_data['goldcoin']) == 0 ? 0 : 1;
        }

        if (!isset($recode_data['typesub']))
        {
            //类型  数据类型包括 
            //0红包  1限时红包、2非限时红包  
            //1金币 1直接领，2签到领，3邀请领
            $recode_data['typesub'] = 1; //默认旧红包领取方式
        }

        $arr["type"] = $recode_data['type'];
        $arr["typesub"] = $recode_data['typesub'];
        $arr["money"] = isset($recode_data['money']) ? $recode_data['money'] : 0;
        $arr["goldcoin"] = isset($recode_data['goldcoin']) ? $recode_data['goldcoin'] : 0;
        $arr['itime'] = $recode_data['itime'];
//        $arr['lon'] = isset($recode_data['lon']) ? $recode_data['lon'] : '';
//        $arr['lat'] = isset($recode_data['lat']) ? $recode_data['lat'] : '';

        //经纬度位置不能获取则随意扔
        $data = self::getUser($user_id);

        $arr['path'] = !empty($recode_data['path']) ? $recode_data['path'] : self::getMyPath($data['system']);
        $arr['nickname'] = $data['nickname'];
        $arr['headimg'] = $data['headimgurl'];

        $redis->lpush($config_websocket['key_all_weal_list'], json_encode($arr));
    }

    //随机一个位置
    public static function getMyPath($system = 1)
    {
        if ($system == 2)
        {
            $version = isset($_GET['version']) ? $_GET['version'] : '';
            $verData = self::getVersion($system);
            if ($version == $verData['ver'])
            {
                return '未知地址';
            }
        }

        return '未知地址';
        $star = ["水星", "金星", "火星", "木星", "土星", "天王星", "海王星", "冥王星"];
        return '来自' . $star[array_rand($star)];
    }

    /**
     * 检查领取福利的频率，不能一个用户同一秒执行同类操作超过1次
     */
    public static function checkWealAct($userId, $itime, $type)
    {
        $cacheKey = 'thb_checkWeal_' . $userId . '_' . $type;
        return self::checkAct($cacheKey, $itime);
    }

    /**
     * 检查消费金币的频率，不能一个用户同一秒执行同类操作超过1次
     */
    public static function checkExpenseAct($userId, $itime, $type)
    {
        $cacheKey = 'thb_checkExpense_' . $userId . '_' . $type;
        return self::checkAct($cacheKey, $itime);
    }

    /**
     * 检查动作
     * @param type $cacheKey
     * @param type $itime
     * @return boolean
     */
    private static function checkAct($cacheKey, $itime)
    {
        $cache = \lib\Cache::init();
        $cacheData = $cache->get($cacheKey);

        //若存在且和当前一致，说明重复
        if (isset($cacheData) && $cacheData == $itime)
        {
            return FALSE;
        } else
        {
            //设置或者覆盖，保留1秒时间
            $cache->set($cacheKey, $itime, 1);

            return TRUE;
        }
    }

    /**
     * 
     * 唯一值
     */
    static function getUniqueId($prefix = null)
    {
        return md5(uniqid($prefix, TRUE) . rand(1, 10000));
    }

    //===================积分商城


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
     *  积分消耗请求的解析方法
     *  当用户进行兑换时，兑吧会发起积分扣除请求，开发者收到请求后，可以通过此方法进行签名验证与解析，然后返回相应的格式
     *  返回格式为：
     *  成功：{"status":"ok", 'errorMessage':'', 'bizId': '20140730192133033', 'credits': '100'}
     *  失败：{'status': 'fail','errorMessage': '失败原因（显示给用户）','credits': '100'}
     */

    static function parseCreditConsume($appKey, $appSecret, $request_array)
    {
        if (empty($request_array["appKey"]) || $request_array["appKey"] != $appKey)
        {
            return ['fail', 'appKey not match'];
//            throw new \Exception("appKey not match");
        }
        if (empty($request_array["timestamp"]) || $request_array["timestamp"] == null)
        {
            return ['fail', 'timestamp can not be null'];
//            throw new \Exception("timestamp can't be null");
        }
        $verify = self::signVerify($appSecret, $request_array);
        if (!$verify)
        {
            return ['fail', 'sign verify fail'];
//            throw new \Exception("sign verify fail");
        }

        return ['ok'];
//        return ['ok', $request_array];
    }

    /*
     *  兑换订单的结果通知请求的解析方法
     *  当兑换订单成功时，兑吧会发送请求通知开发者，兑换订单的结果为成功或者失败，如果为失败，开发者需要将积分返还给用户
     */

    static function parseCreditNotify($appKey, $appSecret, $request_array)
    {
        if (empty($request_array["appKey"]) || $request_array["appKey"] != $appKey)
        {
            return [FALSE, 'appKey not match'];
//            throw new \Exception("appKey not match");
        }
        if (empty($request_array["timestamp"]) || $request_array["timestamp"] == null)
        {
            return [FALSE, 'timestamp can not be null'];
//            throw new \Exception("timestamp can't be null");
        }
        $verify = self::signVerify($appSecret, $request_array);
        if (!$verify)
        {
            return [FALSE, 'sign verify fail'];
//            throw new \Exception("sign verify fail");
        }

//        return $request_array["success"];

        return [$request_array["success"], $request_array["errorMessage"], $request_array["bizId"]];
    }

    /**
     * 兑吧的订单增加
     * @param type $param
     */
    static function addDuibaOrder($userId, $param)
    {
        $db = \lib\DB::init();

        //先判断订单是否重复增加
        $rs = $db->get('thb_order_duiba', 'goid', ['orderNum' => $param['orderNum']]);

        if (!empty($rs))
        {
            //已有该订单，忽略
            return ['fail', 'Repeat orders'];
        }

        $userInfo = self::getUser($userId);

        //用户存在且够扣
        if (!empty($userInfo) && $userInfo['goldcoin'] >= $param['credits'])
        {
            //增加记录
            $param_insert = [
                'orderopenid' => \mod\Hb2::getUniqueId('addDuibaOrder'), //生成时间
                'orderNum' => $param['orderNum'],
                'uid' => $param['uid'],
                'credits' => $param['credits'],
                'itemCode' => !empty($param['itemCode']) ? $param['itemCode'] : '',
                'timestamp' => time(),
                'description' => $param['description'],
                'type' => $param['type'],
                'facePrice' => $param['facePrice'],
                'actualPrice' => $param['actualPrice'],
                'ip' => $param['ip'],
                'waitAudit' => !empty($param['waitAudit']) && $param['waitAudit'] == 'true' ? 1 : 0,
                'params' => $param['params'],
                'sign' => $param['sign'],
                'lon' => empty($userInfo['lon']) ? '' : $userInfo['lon'],
                'lat' => empty($userInfo['lat']) ? '' : $userInfo['lat'],
                'updtime' => time(),
                'beforegold' => 0,
                'aftergold' => 0,
                'sta' => 2, //先为无效，等通知来了再决定是否为成功
            ];

            $db->insert("thb_order_duiba", $param_insert);
            $gcId = $db->id();
            if (!$gcId) return ['fail', 'Record failure'];

            //返回对外订单id，可剩余的金币数
            return ['ok', $param_insert['orderopenid'], $userInfo['goldcoin'] - $param['credits']];
        }else
        {
            return ['fail', 'Users do not exist or do not have enough gold'];
        }
    }

    /**
     * 确认订单成功兑换
     * @param type $param
     * @return boolean|int
     */
    static function affirmDuibaOrder($param)
    {
        $db = \lib\DB::init();

        //先判断订单是否已处理，若未处理才继续
        $rs = $db->get('thb_order_duiba', '*', ['orderNum' => $param['orderNum'], 'orderopenid' => $param['bizId'], 'sta' => 2]);

        if (!empty($rs))
        {

            //开始事务
            $db->query("set autocommit = 0");

            //锁行,防止并发执行
            $userInfo = $db->query("select goldcoin,goldcount from thb_user where user_id = {$rs['uid']} for update")->fetchAll();

            //用户存在且够扣
            if (!empty($userInfo[0]) && $userInfo[0]['goldcoin'] >= $rs['credits'])
            {

                $param_upd['updtime'] = time(); //扣除时间
                $param_upd['beforegold'] = $userInfo[0]['goldcoin']; //扣除前金币
                $param_upd['aftergold'] = $userInfo[0]['goldcoin'] - $rs['credits']; // 扣除后金币
                $param_upd['sta'] = 1; //有效
                //现在是入账的
                //花费金币数为负值,支持为0
                //更新金币数
                $db->update("thb_user", ["goldcoin[-]" => $rs['credits'], "goldcount[+]" => 1], ["user_id" => $rs['uid']]);

                //增加记录
                $db->update("thb_order_duiba", $param_upd, ['goid' => $rs['goid']]);

                $db->query("set autocommit = 1");

                //返回
                $rdata['ret'] = 1;

                //删除用户信息缓存
                $cache = \lib\Cache::init();
                $cache->delete('user_info_' . $rs['uid']);

                return $rdata;
            } else
            {
                return FALSE;
            }
        } else
        {
            return FALSE;
        }
    }

    /**
     * 根据经纬度获取地址
     * @param type $lon
     * @param type $lat
     * @return string
     */
    public static function getPath($lon, $lat)
    {
        //为了保证性能，暂时屏蔽这个请求
//        return NULL;
        $re = null;
        $ak_arr = [
            'UI3bUQeFDLPpkkZq0QIlRtT0',
            'A8pzgtNEdhiak6DtckrnBAbG',
        ];

        $ak = $ak_arr[array_rand($ak_arr, 1)];
        $location = "{$lat},{$lon},";
        $url = 'http://api.map.baidu.com/geocoder/v2/?coordtype=gcj02ll&location=' . $location . '&output=json&pois=1&ak=' . $ak;
        $json = \lib\Util::curlGet($url);

//        \lib\Util::ppr($json);
        if (!empty($json))
        {
            $arr = json_decode($json, TRUE);

            if (!empty($arr) && !empty($arr['result']['addressComponent']))
            {
                $addr = $arr['result']['addressComponent'];
                $province = empty($addr['province']) ? '' : $addr['province'];
                $city = empty($addr['city']) ? '' : $addr['city'];
                $district = empty($addr['district']) ? '' : $addr['district'];

                //避免城市和位置有重复
                $re = $province . $city . str_replace($city, '', $district);
            }
        }
        return $re;
    }

    //========================无限分身的model

    static function wxfsConfig($type)
    {
        $db = \lib\DB::init();
        $re = null;
        $rs = $db->get('xtl_wxfs_config', '*', ['name' => $type]);

        if (!empty($rs))
        {
            if (!empty($rs['value']))
            {
                $re = array_merge($rs, json_decode($rs['value'], true));

                if (!empty($re['link']) && strpos($re['link'], 'http') !== 0)
                {
                    $domain_url = 'http://51qhb.16999.com';
                    $re['link'] = $domain_url . $re['link'];
                }
            } else
            {
                $re = $rs;
            }

            unset($re['value']);
        }

        return $re;
    }

}

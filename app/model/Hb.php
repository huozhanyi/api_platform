<?php
/**
 * 我要偷红包模块
 * @author dalin<lihuanlin@ivali.com>
 * @date 2017-08-16
 */
namespace mod;

class Hb
{
    private static $hbSpaceTime = 1800;         //土豪红包间隔时间30分钟
    private static $hbSpaceTimeFriend = 1800;   //好友红包间隔时间30分钟
    protected static $dataNum = 15;             //每次读取15条数据
    
    /**
     * 检查用户登录状态
     * @return array
     */
    public static function checkAuth($param = false)
    {
        if ( isset($_GET['token']) && isset($_GET['user_id']) && isset($_GET['time']) && isset($_GET['sign']))
        {
            $token = $_GET['token'];
            $userId = $_GET['user_id'];
            $time = $_GET['time'];
            $sign = $_GET['sign'];
            $secretConf = \lib\Util::getConfig('secret');
            $openid =  self::getOpenidById($userId);
            $extData = $param ? self::fliterParam($param) : '';
            //验证token
            if($token != self::setToken($userId, $openid))
            {
                \Flight::json(\lib\Util::apiRes(1, 'ERROR_TOKEN'));
                exit();
            }
            if ( md5($token.$secretConf['thb'].$_GET['time'].$extData) == $sign && ($time + 1800) > time())   
            {
                return $userId;
            }
        }
        \Flight::json(\lib\Util::apiRes(1, 'MISS_AUTH'));exit;
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
        foreach($param as $key => $p)
        {
            if($p !== '')
            $newParam[$key] = $p;
        }
        //排序
        ksort($newParam);
        $string = implode('',$newParam);
        return $string;
    }
    
    /**
     * openid换用户id
     * @param string $openid
     * @return int
     */
    public static function getIdByOpenid( $openid = '' )
    {
        $cache = \lib\Cache::init();
        $cacheData = $cache->get('user_id_'.$openid);
        if ( $cacheData )
        {
            return (int)$cacheData;
        }
        $db = \lib\DB::init();
        $rs = $db->get('thb_user', ['user_id'], ['openid'=>$openid]);
        if ( $rs )
        {
            $cache->set('user_id_'.$openid, $rs['user_id'], 86400);
            $cache->set('user_id_'.$rs['user_id'], $openid, 86400);
        }
        return $rs ? (int)$rs['user_id'] : 0;
    }
    
    
    /**
     * 用户id换openid
     * @param int $userId
     * @return int
     */
    public static function getOpenidById( $userId  )
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'user_id_'.$userId;
        $cacheData = $cache->get($cacheKey);
        if ( $cacheData )
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $rs = $db->get('thb_user', ['openid'], ['user_id'=>$userId]);
        if ( $rs )
        {
            $cache->set($cacheKey, $rs['openid'], 86400*30);
        }
        return $rs ? $rs['openid'] : 0;
    }
    
    /**
     * 获取用户信息
     * @param int $userId
     * @return array
     */
    public static function getUser( $userId = 0 )
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'user_info_'.$userId;
        $cacheData = $cache->get($cacheKey);

        if ( $cacheData )
        {
            return $cacheData;
        }
        
        $db = \lib\DB::init();
        $rs = $db->get('thb_user', '*', ['user_id'=>$userId]);

        if ( $rs )
        {
            $cache->set($cacheKey, $rs, 86400*30);
        }
        return $rs;
    }
    
    /**
     * 添加用户信息
     * @param array $data
     * @return int
     */
    public static function addUser( $data = [] )
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
    public static function editUser( $userId = 0, $data = [] )
    {
        $db = \lib\DB::init();
        $rs = $db->update('thb_user', $data, ['user_id'=>$userId]);
        if ( $rs !== false )
        {
            $cache = \lib\Cache::init();
            $cache->delete('user_info_'.$userId);
        }
        return $rs;
    }
    
    /**
     * 生成token
     * @param int $userId string $openid
     * @return string
     */
    public static function setToken($userId,$openid)
    {
        $token = md5($userId.$openid);
        return $token;
    }
    
    /**
     * 执行偷红包操作
     * @param int $userId int $type
     * @return array $rdata
     */
    public static function snatchHb($userId,$type)
    {
        $db = \lib\DB::init();
        
        //开始事务
        $db->query("set autocommit = 0");
        
        //锁行,防止并发执行
        $userInfo = $db->query("select amount,system from thb_user where user_id = {$userId} for update")->fetchAll();
        
        $preRecord =  self::getPreRecord($userId,$type);
        $spaceTime = $type !=0 ? self::$hbSpaceTimeFriend : self::$hbSpaceTime;
        
        $leaveTime =  ((int)$preRecord['itime'] + $spaceTime) - time() > 0 ? ((int)$preRecord['itime'] + $spaceTime) - time() : 0;
        
        //不要考虑是否确认，到点就重新开抢
        //$confirm = isset($preRecord['confirm']) ? $preRecord['confirm'] : 1;
        
        //如果未达到抢红包时间,或者还没开抢则返回上一次的记录
        if($leaveTime > 0)
        {
            $rdata['hb_id'] = $preRecord['hb_id'];
            $rdata['money'] = (float)$preRecord['money'];
            $rdata['itime'] = $preRecord['itime'];
            $rdata['leave_time'] = $leaveTime;
            
        //符合条件，执行抢红包            
        }else{
        //根据特定算法计算出单次抢到的红包金额
            $money = self::getMoney($userInfo[0]['amount'],$userInfo[0]['system']);
            
            $data = array(
                'user_id' =>$userId,
                'money' =>  $money,
                'day' =>  date("ymd",time()),
                'itime' =>  time(),
                'friend_id' =>$type
            );
            
            //更新用户红包账户金额和数量
            $db = \lib\DB::init();
            $db->insert("thb_record",$data); 
            $hbId = $db->id();
            if(!$hbId)  return false;
            
            $rdata['hb_id'] = $hbId;
            $rdata['money'] = (float)$data['money'];
            $rdata['itime'] = $data['itime'];
            $rdata['leave_time'] = $spaceTime;
            
            //结束实务
            $db->query("set autocommit = 1");
        }
        return $rdata;
    }
    
    /*
     * 确认红包并入库
     * @param int $userId int $hbId
     * @return float $money
     */
    public static function confirmHb($userId,$hbId)
    {
        $db = \lib\DB::init();
        
        $rdata = false;
                
        //开始事务
        $db->query("set autocommit = 0");
        
        //锁行,防止并发执行
        $userInfo = $db->query("select user_id from thb_user where user_id = {$userId} for update")->fetchAll();
        
        $result = $db->get("thb_record",["money"],["user_id"=>$userId,"hb_id"=>$hbId,"confirm"=>0]);
        if($result)
        {
            $db->update("thb_record",["confirm"=>1],["user_id"=>$userId,"hb_id"=>$hbId]);
            $db->update("thb_user",["amount[+]" => $result['money'],"count[+]" => 1],["user_id"=>$userId]); 
            //删除用户信息缓存
            $cache = \lib\Cache::init();
            $cache->delete('user_info_'.$userId);
            $rdata['ret'] = 1;
        }
            //结束实务
            $db->query("set autocommit = 1");
            return $rdata;
    }
    
    
    /**
     * 根据特定算法计算出单次抢到的红包金额
     * @param float $amount int $system
     * @return float $money
     */
    private static function getMoney($amount,$system = 1)
    {
            if($system == 2)
            {
                $version = isset($_GET['version']) ? $_GET['version'] : '';
                $verData = self::getVersion($system);

                //设备为IOS并且为审核状态的只能抢小额红包(审核状态的红包不能为大额红包，麻囵烦)
                if($version == $verData['ver'])
                {
                    $money = round(mt_rand(1,100)/100,2);
                    return $money;
                }
            }
        
            //用户总金额大于500的话，用户有1/10的几率抢到0元红包
            if($amount >= 500)
            {
                $zero = mt_rand(0,6);
                if($zero == 0)  return 0;
            }
            
            //白名单用户可以抢到1000并提现
            if($_GET['user_id'] == 1)
            {
                if($amount < 500)
                {
                    $money = round(mt_rand(500,1000)/100,2);
                }elseif(500 <= $amount &&  $amount < 700)
                {
                    $money = round(mt_rand(1,300)/100,2);
                }elseif(700 <= $amount &&  $amount < 960)
                {
                    $money = round(mt_rand(1,10)/100,2);
                }else{
                    //有三分之一机会抢不到
                    //$zero = mt_rand(0,2);
                    //if($zero == 0)  return 0;
                     $money = round(mt_rand(1,9)/100,2);
                }
            //普通用户不能抢到1000也不能提现
            }else{
                if($amount < 500)
                {
                    $money = round(mt_rand(500,1000)/100,2);
                }elseif(500 <= $amount &&  $amount < 700)
                {
                    $money = round(mt_rand(1,300)/100,2);
                }elseif(700 <= $amount &&  $amount < 800)
                {
                    $money = round(mt_rand(1,20)/100,2);
                }elseif(800 <= $amount &&  $amount < 960)
                {
                    $money = 0.01;
                }else{
                    $money = 0;
                }
            }
        return $money;
    }
    
    /**
     * 获取上一次的红包记录
     * @param int $userId int $type
     * @return 
     */
    private static function getPreRecord($userId,$type)
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
        $result = $db->get("thb_record","*",["user_id"=>$userId,"friend_id"=>$type,"hbtype"=>1, "ORDER"=>["hb_id"=>"DESC"]]);
        if($result)
        {
        // $cache->set($cacheKey,$result,1800);
            return $result;
        }else{
            return false;
        }
    }
    
    /**
     * 获取倒计时时间
     * @param int $userId int $type
     * @return int
     */
    private static function getLeaveTime($userId,$type)
    {
        $preRecord =  self::getPreRecord($userId,$type);
        $spaceTime = $type != 0 ? self::$hbSpaceTimeFriend : self::$hbSpaceTime;
        return ((int)$preRecord['itime'] + $spaceTime) - time() > 0 ? ((int)$preRecord['itime'] + $spaceTime) - time() : 0;
    }
    
    /**
     * 获取指定用户红包记录
     * @param int $userId int $page
     * @return 
     */
    public static function getRecord($userId,$page)
    {
        $dataNum = self::$dataNum;  
        $offset  = $page * $dataNum;
        $db = \lib\DB::init();
        $result = $db->select("thb_record",["hb_id","money","itime","friend_id"],["user_id"=>$userId,"confirm"=>1,"ORDER"=>["hb_id"=>"DESC"],"LIMIT"=>[$offset,$dataNum]]);
        if(!$result)
            $result = $db->select("thb_record_old",["hb_id","money","itime","friend_id"],["user_id"=>$userId,"confirm"=>1,"ORDER"=>["hb_id"=>"DESC"],"LIMIT"=>[$offset,$dataNum]]);    
        
        if($result)
        {
            $data = array();
            $userInfo = self::getUser($userId);
            foreach($result as $key => $res)
            {
                if($res['friend_id'] == 0)
                {
                    $nickname = $userInfo['nickname'];
                    $headimgurl = $userInfo['headimgurl'];
                    //广告红包
                }elseif($res['friend_id'] < 0){
                    $fixedHbInfo = self::fixedHb();
                    $nickname = $fixedHbInfo[$res['friend_id']]['nickname'];
                    $headimgurl = $fixedHbInfo[$res['friend_id']]['headimgurl'];
                }else{
                    $friendInfo = self::getUser($res['friend_id']);
                    $nickname = isset($friendInfo['nickname']) ? $friendInfo['nickname'] : '';
                    $headimgurl = isset($friendInfo['headimgurl']) ? $friendInfo['headimgurl'] : '';
                }
                $data[$key]['nickname'] = $nickname;
                $data[$key]['headimgurl'] = $headimgurl;
                $data[$key]['hb_id'] = $res['hb_id'];
                $data[$key]['money'] = $res['money'];
                $data[$key]['itime'] = $res['itime'];
            }
            return $data;
        }else{
            return false;
        }
    }
    
    /**
     * 关联好友关系
     * @param int $friendId int $userId
     * @return
     */
    public static function relateFriends($friendId,$userId)
    {
        if($friendId == $userId)
            return false;
        $db = \lib\DB::init();
        $cache = \lib\Cache::init();
        $friendsData = $db->get("thb_user",["friends"],["user_id"=>$friendId]);
        //如果不存在该好友，则不作任何操作
        if(!$friendsData) 
            return false;
        $friendArr = (array)json_decode($friendsData['friends'],true);
        if(!in_array($userId, $friendArr))
        {
            array_push($friendArr, $userId);
            $friends = json_encode($friendArr);
            $db->update("thb_user",["friends"=>$friends,"friendsnum[+]" => 1],["user_id"=>$friendId]);
            $cache->delete('user_info_'.$friendId);
        }
        
        $friendsData = $db->get("thb_user",["friends"],["user_id"=>$userId]);
        $friendArr = (array)json_decode($friendsData['friends'],true);
        if(!in_array($friendId, $friendArr))
        {
            array_push($friendArr, $friendId);
            $friends = json_encode($friendArr);
            $db->update("thb_user",["friends"=>$friends,"friendsnum[+]" => 1],["user_id"=>$userId]);
            $cache->delete('user_info_'.$userId);
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
        $data['leave_time'] = self::getLeaveTime($userId,0);
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
        $friends = json_decode($userInfo['friends'],true);
        $fixedData = self::fixedHb();
        
        //只获取6个好友
        $friends = $friends ? array_slice($friends,0,10) : array();
        $arrayList =  array_merge($friends,$fixedData);
        
        foreach($arrayList as $key=>$list)
        {
            if(!isset($list['friend_id']))
            {
                $friendUserInfo = self::getUser($list);
                $data[$key]['friend_id'] = $list;
                $data[$key]['nickname'] = $friendUserInfo['nickname'];
                $data[$key]['headimgurl'] = $friendUserInfo['headimgurl'];
            }else{
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
        if ( $cacheData )
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $result = $db->select('thb_ad', ["type","name","imgurl"]);
        if ( $result )
        {
            $data = array();
            foreach($result as $key => $res)
            {
                $data[$res['type']]['friend_id'] = (int)$res['type'];
                $data[$res['type']]['nickname'] = $res['name'];
                $data[$res['type']]['headimgurl'] = $res['imgurl'];
            }
            $cache->set($cacheKey, $data, 86400*30);
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
        if ( $cacheData )
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $data = $db->sum("thb_user","count",["count[>]"=>0]);
        if($data)
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
        $cacheKey = 'thb_version_'.$system;
        $cacheData = $cache->get($cacheKey);
        if ( $cacheData )
        {
            return $cacheData;
        }
        $data = $db->get('thb_version', ["ver","changlog",'md5','link','force'],['id'=>$system]);
        if($data)
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
    public static function deposit($userId,$data)
    {
        $db = \lib\DB::init();
        
        //开始事务
        $db->query("set autocommit = 0");
        
        //锁行,防止并发执行
        $userInfo = $db->query("select amount from thb_user where user_id = {$userId} for update")->fetchAll();
        
        $amount = $userInfo[0]['amount'];
        if($amount < $data['money'])
        {
            return false;
        }
        
        $data['itime'] = time();
        $data['user_id'] = $userId;
        
        //验证码不用加入数据库
        unset($data['vercode']);
        $db->insert("thb_deposit",$data);
        $id = $db->id();
        if($id)
        {
            $db->update("thb_user",["amount[-]" => $data['money']],["user_id"=>$userId]);
            
            //结束实务
            $db->query("set autocommit = 1");
            
            //删除用户信息缓存
            $cache = \lib\Cache::init();
            $cache->delete('user_info_'.$userId);
            
            $rdata['ret'] = 1;
            return $rdata;
        }else{
            return false;
        }
    }
    
    /*
     * 获取验证码
     * @param int $tpl_id 模板id string $tpl_value 模板内容（#code#=123456）
     * @return array|boolen
     * @remark 模板 51541 【小陀螺】您的验证码是#code#，10分钟内有效，祝您生活愉快！
     */
    public function getVerCode($mobile = 0, $tpl_id = 0, $tpl_value = '')
    {
         if(!$mobile)
        {
            return false;
        }
        $config = \lib\Util::getConfig('juhe');
        
        $curl = new \Curl\Curl();
        $curl->get('http://v.juhe.cn/sms/send', array(
            'mobile'=> $mobile,
            'tpl_id'=> $tpl_id,
            'tpl_value'=> urlencode($tpl_value),
            'key'=> $config['ver_code_key']
        ));
        if ($curl->error) {
            return false;
        }
        else {
            $curl_data = $curl->response;
        }
        return json_decode($curl_data, true);
    }
    
    /**
     * 我的提现记录
     * @param int $userId int $page
     * @return array
     */
    public function getDepositList($userId,$page)
    {
        $dataNum = self::$dataNum;  
        $offset  = $page * $dataNum;
        $db = \lib\DB::init();
        $data = $db->select("thb_deposit",["deposit_id","money","itime","status"],["user_id"=>$userId,"ORDER"=>["deposit_id"=>"DESC"],"LIMIT"=>[$offset,$dataNum]]);
        if($data)
        {
            return $data;
        }else{
            return false;
        }
    }
    
    /**
     * 获取全部提现记录
     * @param int $page
     * @return array
     */
    public function getAllDepositList()
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
        $data = $db->select("thb_deposit",["user_id","deposit_id","money","itime","path"],["STATUS"=>1,"ORDER"=>["deposit_id"=>"DESC"],"LIMIT"=>90]);
        if($data)
        {
            foreach($data as $key=>$res)
            {
                $resData[$key]['deposit_id'] = $res['deposit_id'];
                $resData[$key]['money'] = $res['money'];
                $resData[$key]['itime'] = $res['itime'];
                //获取用户信息
                $userInfo = self::getUser($res['user_id']);
                $resData[$key]['nickname'] = $userInfo['nickname'];
                $resData[$key]['headimgurl'] = $userInfo['headimgurl'];
            }
            $robotData = json_decode($robotData,true);
            $resData = array_merge($resData,$robotData);
           
            array_multisort(array_column($resData,'itime'),SORT_DESC,$resData);
            return $resData;
        }else{
            return false;
        }
    }
    
    /**
     * 获取最近抢到的红包记录
     * @param int $limit
     * @return array
     */
    public function getRecently($limit = 10)
    {
        $cache = \lib\Cache::init();
        $cacheKey = 'thb_recently';
        $cacheData = $cache->get($cacheKey);
        if ( $cacheData )
        {
            return $cacheData;
        }
        $db = \lib\DB::init();
        $result = $db->select("thb_record",["hb_id","user_id","money","itime"],["confirm"=>1,"money[>]"=>0.1,"ORDER"=>["hb_id"=>"DESC",],"LIMIT"=>$limit]);
        foreach($result as $key => $res)
        {
             $userInfo = self::getUser($res['user_id']);
             $data[$key]['hb_id'] = $res['hb_id'];
             $data[$key]['money'] = $res['money'];
             $data[$key]['itime'] = $res['itime'];
             $data[$key]['nickname'] = $userInfo['nickname'];
        }
        if($data)
        {
            //缓存3分钟
            $cache->set($cacheKey,$data,180);
            return $data;
        }else{
            return false;
        }
    }
    
    /**
     * 获取应用配置
     * 
     * @param int $key 键值
     * @return array|false 
     */
    public static function  getConfig($key)
    {
        $cache     = \lib\Cache::init();
        $cacheKey  = 'thb_config_'.$key; 
        $cacheData = $cache->get($cacheKey);
        if ( $cacheData )
            return $cacheData;
         
        $db = \lib\DB::init();
        $configData = $db->get('thb_config', array('value'),array('name'=>$key));
        if(!empty($configData['value'])){
            $redata = json_decode($configData['value'],true);
            $cache->set($cacheKey, $redata,86400*30);
            return $redata;
        }else{
            return false;
        }
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

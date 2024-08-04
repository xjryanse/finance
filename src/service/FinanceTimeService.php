<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\user\service\UserService;
use xjryanse\logic\Datetime;
use xjryanse\logic\Arrays;
use xjryanse\logic\Cachex;
use Exception;

/**
 * 1: baoOrder 锁定包车订单不可调整客户；下单人；单价；
 * 2: baoFinanceStaffFee 锁定包车订单费用不可报销
 * 3: baoBusDriverFee 锁定司机补贴不可调整。
 * 4: 工资使用salary板块的lock
 * 
 */
class FinanceTimeService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\finance\service\time\FieldTraits;
    use \xjryanse\finance\service\time\TriggerTraits;
    use \xjryanse\finance\service\time\BatchManageTraits;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceTime';
    //直接执行后续触发动作
    protected static $directAfter = true;

    /**
     * 改写MainModelTrait方法
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     * @param type $withSum
     * @return type
     */
    public static function paginate($con = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {
        //初始化账期数据，节省人工添加的承包
        self::init();
        return self::paginateX($con, $order, $perPage, $having, $field, $withSum);
    }

    /**
     * 初始化账期数据
     */
    public static function init() {
        $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        $info = self::mainModel()->where($con)->order('to_time desc')->limit(1)->find();

        $endTime = date('Y-m-d H:i:s');
        //开始日期，已有账期最后一个日期+一天
        $startTime = $info ? date('Y-m-d H:i:s', strtotime($info['to_time']) + 86400) : date('Y-01-01 00:00:00');

        $arr        = Datetime::getWithinMonth($startTime, $endTime);
        
        $keysArr    = FinanceTimeKeyService::keysForInit();

        foreach ($arr as $v) {
            $data = [];
            $data['belong_time'] = $v;
            $data['from_time'] = $v . '-01 00:00:00';
            $data['to_time'] = date('Y-m-d 23:59:59', strtotime('+1 month -1 day', strtotime($v . '-01')));
            // 子事项
            foreach($keysArr as $keyItem){
                $data['dept_id']    = Arrays::value($keyItem, 'dept_id');
                $data['thing_key']  = Arrays::value($keyItem, 'thing_key');
                self::save($data);
            }
            $data['dept_id']    = null;
            $data['thing_key']  = null;
            
            self::save($data);
        }
    }

    /**
     * 20220617:设定缓存；一般用于锁账后；
     * 20240522:直接清理掉，比较符合习惯
     */
    protected static function setLockTimeArrCache() {
        $cacheKey = __CLASS__ . 'getLockTimesArr';
        return Cachex::rm($cacheKey, function() {
                    return self::lockTimeArrDb();
                }, true);
    }

    /**
     * 获取锁定时间段
     * @param type $cacheUpdate     
     * @return type
     */
    protected static function getLockTimesArrCache() {
//        $cacheKey = __CLASS__ . 'getLockTimesArr';
//        return Cachex::funcGet($cacheKey, function() {
//                    return self::lockTimeArrDb();
//                }, true);
                
        return self::lockTimeArrDb();
    }

    /**
     * 20220617;从数据库获取
     * @return type
     */
    protected static function lockTimeArrDb() {
        $con[] = ['time_lock', '=', 1];
        // 20240522:3秒缓存
        $lists = self::lists($con,'','*','3');
        return $lists ? $lists->toArray() : [];
    }

    /**
     * 传入一个时间，获取被锁定的账期名
     * @param type $time
     * @param type $thingKey
     * @param type $data
     * @return bool
     */
    public static function isTimeLock($time, $thingKey='', $deptId = '') {
        $lockArr = self::getLockTimesArrCache();
        foreach ($lockArr as &$v) {
            // 时间范围匹配
            if ($v['from_time'] <= $time && $v['to_time'] >= $time) {
                // 总的锁，则锁
                if(!$v['thing_key'] || $v['thing_key'] == 'ALL'){
                    return $v['id'];
                }

                // 事项锁
                if($v['thing_key'] == $thingKey && $v['dept_id'] == $deptId){
                    return $v['id'];
                }

            }
        }
        return false;
    }

    /**
     * 校验时间是否被锁
     * @param type $time
     * @param type $thingKey    20240513增
     * @param type $deptId      20240513增
     * @throws Exception
     */
    public static function checkLock($time, $thingKey='', $deptId='') {
        $lockTimeId = self::isTimeLock($time, $thingKey, $deptId);
        if ($lockTimeId) {
            $info = FinanceTimeService::getInstance($lockTimeId)->get();
            $lockUserId = $info['lock_user_id'];
            $userInfo = UserService::getInstance($lockUserId)->get();
            $namePhone = Arrays::value($userInfo, 'namePhone');
            throw new Exception('账期' . $info['belong_time'] . '已被"' . $namePhone . '"锁定，请联系财务'.$thingKey.$deptId);
        }
    }


}

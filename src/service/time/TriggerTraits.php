<?php

namespace xjryanse\finance\service\time;

use xjryanse\logic\Arrays;
use xjryanse\logic\Datetime;
use Exception;
/**
 * 分页复用列表
 */
trait TriggerTraits{

    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        
    }

    /**
     * 钩子-保存后
     */
    public static function extraAfterSave(&$data, $uuid) {

        //20220617
        self::setLockTimeArrCache();
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        if (Arrays::value($data, 'time_lock')) {
            $info = self::getInstance($uuid)->get();
            if (!Datetime::isExpire($info['to_time'])) {
                throw new Exception('时间' . $info['to_time'] . '未过，还可能产生业务数据，不可关账');
            }
            $data['lock_user_id'] = session(SESSION_USER_ID);
        } else {
            if (isset($data['time_lock'])) {
                $data['lock_user_id'] = null;
            }
        }
    }

    /**
     * 钩子-更新后
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        //20220617
        self::setLockTimeArrCache();
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete() {
        
    }

    /**
     * 钩子-删除后
     */
    public function extraAfterDelete() {
        
    }
    
    
    public static function ramPreSave(&$data, $uuid) {
        self::addBelongTimeData($data);
    }
    
    /**
     * 钩子-更新前
     */
    public static function ramPreUpdate(&$data, $uuid) {
        if (Arrays::value($data, 'time_lock')) {
            $info = self::getInstance($uuid)->get();
            if (!Datetime::isExpire($info['to_time'])) {
                throw new Exception('时间' . $info['to_time'] . '未过，还可能产生业务数据，不可关账');
            }
            $data['lock_user_id'] = session(SESSION_USER_ID);
        }

        if (isset($data['time_lock'])) {
            $data['lock_user_id'] = null;
        }
        
        self::addBelongTimeData($data);
    }
    
    /**
     * 钩子-更新后
     */
    public static function ramAfterUpdate(&$data, $uuid) {
        //20220617
        self::setLockTimeArrCache();
    }
    /**
     * 
     * @param type $data
     */
    private static function addBelongTimeData(&$data){
        if(Arrays::value($data, 'belong_time')){
            $con    = [];
            $con[]  = ['belong_time','=',$data['belong_time']];
            $tInfo  = self::where($con)->find();

            if($tInfo && !isset($data['from_time'])){
                $data['from_time'] = Arrays::value($tInfo, 'from_time');
            }

            if($tInfo && !isset($data['to_time'])){
                $data['to_time'] = Arrays::value($tInfo, 'to_time');
            }
        }

        return $data;
    }
}

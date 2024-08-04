<?php

namespace xjryanse\finance\service\time;

use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Debug;
/**
 * 批量管理复用
 */
trait BatchManageTraits{
    /**
     * 生成事项key，带部门
     * @param type $thingKey
     * @param type $deptId
     */
    private static function gThingKey($thingKey, $deptId = ''){
        $tK = $thingKey ?:'ALL';

        if($deptId){
            $tK .='_'.$deptId;
        }
        return $tK;
    }
    /**
     * 20240515
     * @return string
     */
    protected static function batchThingKeys(){
        $lists = self::where()->field('dept_id,thing_key')
                ->group('dept_id,thing_key')
                ->select();
        $arr = $lists ? $lists->toArray() : [];
        // 初始化已有全部key数据
        $keys = [];
        foreach($arr as $v){
            $keys[] = self::gThingKey(Arrays::value($v, 'thing_key'), Arrays::value($v, 'dept_id'));
        }
        return $keys;
    }
    
    /**
     * 20240514:
     * 时段查询，用于批量编辑
     */
    public static function findByTime($param){
        $belongTime = Arrays::value($param, 'belong_time');
        
        $keys = self::batchThingKeys();
        // 初始化已有全部key数据
        $data = ['belong_time'=>$belongTime];
        foreach($keys as $thingKey){
            $data[$thingKey] = '0';
        }

        $con    = [];
        $con[]  = ['belong_time','=',$belongTime];
        $all    = self::where($con)->select();
        // 拼接已有数据
        foreach($all as $r){
            $thingKey = self::gThingKey(Arrays::value($r, 'thing_key'), Arrays::value($r, 'dept_id'));
            // 20240514:前端radio无法处理number，todo:前端优化
            $data[$thingKey] = (string)$r['time_lock'];
        }
        return $data;
    }
    /**
     * 批量管理的保存
     */
    public static function batchManageSave($param){

        DataCheck::must($param, ['belong_time']);

        $belongTime = Arrays::value($param, 'belong_time');
        $keys       = self::batchThingKeys();

        $data       = Arrays::getByKeys($param, $keys);
        
        foreach($data as $k=>$v){
            self::kDataSave($belongTime, $k, $v);
        }
        // self::setLockTimeArrCache();
        return true;
    }
    /**
     * 20240515:key和数据保存
     */
    private static function kDataSave($belongTime, $key, $value){
        $arr = explode('_',$key);
        // 20240515:处理总开关
        if($arr[0] == 'ALL'){
            $arr[0] = '';
        }
        
        $data['thing_key']      = Arrays::value($arr, 0) ? : '';
        $data['dept_id']        = Arrays::value($arr, 1) ? : '';
        $data['belong_time']    = $belongTime;
        
        $id = self::commGetIdEg($data);

        $data['time_lock'] = $value;

        return self::getInstance($id)->updateRam($data);
    }

}

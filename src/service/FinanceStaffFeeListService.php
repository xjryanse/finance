<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\bus\service\BusFixService;
use xjryanse\logic\Arrays;
use Exception;

/**
 * 
 */
class FinanceStaffFeeListService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;
    
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFeeList';
    //直接执行后续触发动作
    protected static $directAfter = true;

    use \xjryanse\finance\service\staffFeeList\FieldTraits;
    use \xjryanse\finance\service\staffFeeList\TriggerTraits;
    use \xjryanse\finance\service\staffFeeList\PaginateTraits;
    // 20240326：来源表
    use \xjryanse\finance\service\staffFeeList\FromTableTraits;

    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function ($lists) use ($ids) {
                    return $lists;
                }, true);
    }


    /**
     * 更新冗余
     * @return type
     */
    public function doRedundUpdate(){
        $data = $this->get();
        self::redunFields($data, $this->uuid);
        return $this->doUpdateRam($data);
    }
    
    /**
     * 计算feeArr;
     */
    public static function calFeeIdArr($feeId){
        $cond           = [];
        $cond[]         = ['fee_id', 'in', $feeId];
        $feeListsObj    = self::where($cond)->select();
        $feeLists       = $feeListsObj ? $feeListsObj->toArray() : [];

        return array_column($feeLists, 'money', 'fee_type_id');
    }
    
    /**
     * 20240102：数据搬迁
     */
    public function doMigrate(){
        $info = $this->get();
        if($info['fee_type'] !='weiXiu'){
            throw new Exception('类型不匹配');
        }
        $con = [];
        $con[] = ['from_table','=',self::getTable()];
        $con[] = ['from_table_id','=',$this->uuid];
        $has = BusFixService::where($con)->count();
        if($has){
            throw new Exception('数据已搬迁');
        }

        $feeId      = Arrays::value($info,'fee_id');
        $feeInfo    = FinanceStaffFeeService::getInstance($feeId)->get();

        $data['bus_id']         = Arrays::value($info, 'bus_id');
        $data['prize']          = Arrays::value($info, 'money');
        $data['fix_time']       = Arrays::value($feeInfo, 'apply_time');
        $data['adm_file_id']    = Arrays::value($feeInfo, 'annex');
        $data['file_id']        = Arrays::value($feeInfo, 'file');
        $data['pay_by']         = '';
        $data['driver_id']      = Arrays::value($feeInfo, 'user_id');
        $data['payer_id']       = Arrays::value($feeInfo, 'user_id');
        $data['from_table']     = self::getTable();
        $data['from_table_id']  = $this->uuid;
        
        return BusFixService::saveGetIdRam($data);
    }

}

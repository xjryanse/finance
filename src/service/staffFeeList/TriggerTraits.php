<?php

namespace xjryanse\finance\service\staffFeeList;

use xjryanse\finance\service\FinanceStaffFeeService;
use xjryanse\finance\service\FinanceStaffFeeTypeService;
use xjryanse\logic\Arrays;
/**
 * 
 */
trait TriggerTraits{
    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    public static function ramPreSave(&$data, $uuid) {
        self::redunFields($data, $uuid);
        // 如果没有车辆，且有单据号，取单据号的车辆
        if(!Arrays::value($data, 'bus_id')){
            $data['bus_id'] = FinanceStaffFeeService::getInstance($data['fee_id'])->fBusId();
        }
    }
    
    public static function ramPreUpdate(&$data, $uuid) {
        self::redunFields($data, $uuid);
    }
    /**
     * 
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterSave(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if ($info['fee_id']) {
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdateRam();
        }
    }

    public static function ramAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if ($info['fee_id']) {
            $upData = is_object($info) ? $info->toArray() : [];
            FinanceStaffFeeService::getInstance($info['fee_id'])->objAttrsUpdate('financeStaffFeeList', $uuid, $upData);
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdateRam();
        }
    }

    public function ramPreDelete() {
        $info = $this->get();
        $staffFeeInfo = FinanceStaffFeeService::getInstance($info['fee_id'])->get();
        if ($staffFeeInfo['has_settle']) {
            throw new Exception('报销单已支付不可删');
        }
        if ($info['fee_id']) {
            FinanceStaffFeeService::getInstance($info['fee_id'])->objAttrsUnSet('financeStaffFeeList', $this->uuid);
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdateRam();
            // 父级是空的，直接删了
            if(!FinanceStaffFeeService::getInstance($info['fee_id'])->objAttrsList('financeStaffFeeList')){
                FinanceStaffFeeService::getInstance($info['fee_id'])->doDeleteRam();
            }
        }
    }

    /**
     * 钩子-删除后
     */
    public function ramAfterDelete() {
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    /**
     * 钩子-更新后
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if ($info['fee_id']) {
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdate();
        }
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
    
    protected static function redunFields(&$data, $uuid){
        $feeId = isset($data['fee_id']) 
                ? $data['fee_id'] 
                : self::getInstance($uuid) ->fFeeId();
        if($feeId){
            $data['apply_time'] = FinanceStaffFeeService::getInstance($feeId)->fApplyTime();
            $data['order_id']   = FinanceStaffFeeService::getInstance($feeId)->fOrderId();
            $data['bao_bus_id'] = FinanceStaffFeeService::getInstance($feeId)->fSubId();
            $data['has_settle'] = FinanceStaffFeeService::getInstance($feeId)->fHasSettle();
            $data['user_id']    = FinanceStaffFeeService::getInstance($feeId)->fUserId();
        }
        if(Arrays::value($data, 'fee_type') && !Arrays::value($data, 'fee_type_id') ){
            $data['fee_type_id'] = FinanceStaffFeeTypeService::keyToId($data['fee_type']);
        }
        if(Arrays::value($data, 'fee_type_id') && !Arrays::value($data, 'fee_type') ){
            $data['fee_type'] = FinanceStaffFeeTypeService::getInstance($data['fee_type_id'])->fFeeKey();
        }
        
        return $data;
    }
}

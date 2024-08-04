<?php

namespace xjryanse\finance\service\staffFeeList;

use xjryanse\finance\service\FinanceStaffFeeTypeService;
use xjryanse\finance\service\FinanceStaffFeeService;
use xjryanse\logic\Debug;
/**
 * 
 */
trait FromTableTraits{

    /**
     * 数据写入来源表
     * @param type $fromTable
     * @param type $fromTableId
     * @param type $feeType
     * @param type $money
     * @param type $data
     * 车辆；申请时间；用户；订单号；趟次号……
     */
    public static function dataToStaffFee($fromTable, $fromTableId, $feeType, $money, $data = []){
        $con        = [];
        $con[]      = ['from_table','=',$fromTable];
        $con[]      = ['from_table_id','=',$fromTableId];

        $feeTypeId  = FinanceStaffFeeTypeService::keyToId($feeType);
        $con[]      = ['fee_type_id','=',$feeTypeId];
        
        $lists = self::listSetUudata($con);        
        $id = $lists ? $lists[0]['id'] : '';

        if(!$id){
            $data['money']          = $money;
            // 写入报销表
            $feeId          = FinanceStaffFeeService::saveGetIdRam($data);

            $data['fee_id']         = $feeId;
            $data['from_table']     = $fromTable;
            $data['from_table_id']  = $fromTableId;
            $data['fee_type_id']    = $feeTypeId;
            $id = self::saveGetIdRam($data);
        } else {
            $data['money']          = $money;
            self::getInstance($id)->updateRam($data);
        }
        return $id;
    }
    /**
     * 清理源表数据
     */
    public static function fromTableDataClear($fromTable,$fromTableId){
        // 删除报销明细
        $con    = [];
        $con[]  = ['from_table', '=', $fromTable];
        $con[]  = ['from_table_id', '=', $fromTableId];
        // $arr    = self::where($con)->select();
        $arr    = self::listSetUudata($con);
        // todo:删除
        foreach($arr as $v){
            self::getInstance($v['id'])->deleteRam();
        }
        return true;
    }
    
}

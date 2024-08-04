<?php

namespace xjryanse\finance\service\staffFee;

use xjryanse\logic\Arrays;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceStaffFeeListService;

/**
 * 
 */
trait ListTraits{
    /**
     * 查看指定账单旗下的报销明细
     * @param type $param
     */
    public static function listByStatementId($param){
        $statementId = Arrays::value($param, 'statement_id');
        
        $belongTableIds = FinanceStatementOrderService::calStatementBelongTableIds($statementId);
        
        $con    = [];
        $con[]  = ['id','in',$belongTableIds];
        $lists  = self::where($con)->select();

        $conf = [['a.fee_id','in',$belongTableIds]];
        $arr = FinanceStaffFeeListService::mainModel()->where($conf)
                ->alias('a')
                ->join('w_finance_staff_fee_type b','a.fee_type_id = b.id')
                ->group('a.fee_id')
                ->column('GROUP_CONCAT(fee_name)','a.fee_id');

        $listsArr = $lists ? $lists->toArray() : [];
        
        foreach($listsArr as &$v){
            $v['feeName'] = Arrays::value($arr, $v['id']);
        }
        
        return $listsArr;
    }
}

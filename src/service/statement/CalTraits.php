<?php

namespace xjryanse\finance\service\statement;

use xjryanse\logic\Debug;
use xjryanse\logic\Arrays2d;
/**
 * 分页复用列表
 */
trait CalTraits{
    /**
     * 计算一个入账流水，对应了几笔账单
     */
    public static function calAccountLogStatementCount($accountLogId){
        $con = [];
        $con[] = ['account_log_id','=',$accountLogId];
        return self::where($con)->count();
    }
    
    /**
     * 20231117
     * @param type $ids
     * @return type
     */
    public static function calStatementTypeByIds($ids){
        $con    = [];
        $con[]  = ['id','in',$ids];
        $statementTypes = self::where($con)->column('distinct statement_type');
        return implode(',',$statementTypes);
    }
    /**
     * 计算账单的订单类型
     * 20240303
     */
    public function calStatementOrderType(){
        $lists      = $this->objAttrsList('financeStatementOrder');

        $orderTypes = Arrays2d::uniqueColumn($lists, 'order_type');

        return implode(',',$orderTypes);
    }
}

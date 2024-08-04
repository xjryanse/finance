<?php

namespace xjryanse\finance\service\statementOrder;

use xjryanse\finance\service\FinanceStatementService;
use xjryanse\logic\Arrays2d;
/**
 * 计算类
 */
trait CalTraits{
    
    /**
     * 统计待结算
     * @param type $con
     * @return type
     */
    public static function sumToPay($con = []){
        $con[] = ['is_needpay','=',1];
        $con[] = ['has_settle','=',0];
        return self::where($con)->sum('need_pay_prize');
    }
    /**
     * 统计待结算(客户维度)
     */
    public static function sumToPayByCustomer($customerId){
        $con[] = ['customer_id','in',$customerId];
        return self::sumToPay($con);
    }
    
    /**
     * 统计待结算(用户维度)
     */
    public static function sumToPayByUser($userId){
        $con[] = ['user_id','in',$userId];
        return self::sumToPay($con);
    }
    /**
     * 计算账单的statement_type值
     * @createTime 2023-11-13
     */
    public static function calStatementTypeByStatementId($statementId){
        $lists          = FinanceStatementService::getInstance($statementId)->objAttrsList('financeStatementOrder');
        $statementTypes = Arrays2d::uniqueColumn($lists,'statement_type');
        return count($statementTypes) >1 ? 'mixed':$statementTypes[0];
    }
    /**
     * 计算账单来源表id数组
     * @param type $statementId
     */
    public static function calStatementBelongTableIds($statementId){
        $con            = [];
        $con[]          = ['statement_id','in',$statementId];

        $belongTableIds = self::where($con)->column('belong_table_id');
        return $belongTableIds;
    }
    
}

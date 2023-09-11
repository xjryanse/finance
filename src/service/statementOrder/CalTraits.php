<?php

namespace xjryanse\finance\service\statementOrder;

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

}

<?php

namespace xjryanse\finance\service\statementOrder;

/**
 * 计算类
 */
trait BuyerTraits{
    /**
     * 20240904:提取用户应支付账单明细
     */
    public static function buyerNeedPayStatementOrder($orderId, $cond = [] ){
        $cond[] = ['order_id','=',$orderId];
        //客户应支付账单
        $cond[] = ['has_settle','=',0];
        $cond[] = ['statement_cate','=','buyer'];
        $buyerNeedPayStatement = self::mainModel()->master()->where($cond)->order('id desc')->find();
        return $buyerNeedPayStatement;
    }

}

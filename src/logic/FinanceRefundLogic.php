<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceIncomeService;
use xjryanse\finance\service\FinanceIncomeOrderService;
use xjryanse\finance\service\FinanceRefundService;
use xjryanse\order\service\OrderService;
use Exception;
/**
 * 退款逻辑
 */
class FinanceRefundLogic
{
    /**
     * 发起退款
     * @param type $orderId     订单号
     * @param type $financeSn   收款单号
     * @param type $refundMoney 退款金额
     */
    public static function refund( $orderId ,$financeSn,$refundMoney )
    {
        FinanceRefundService::checkTransaction();
        //获取收款单id
        $incomeId               = FinanceIncomeService::snToId( $financeSn );        
        //订单号和收款单号查询收款单id：
        $con[] = ['order_id','=',$orderId];
        $con[] = ['income_id','=',$incomeId];
        $financeOrder   = FinanceIncomeOrderService::find( $con );
        $finance        = FinanceIncomeService::get( $financeOrder['income_id']);
        
        //获取订单的已付金额-已退金额。小于0报错。
        $orderInfo = OrderService::getInstance( $orderId )->get();
        if( $orderInfo['pay_prize'] < ($orderInfo['refund_prize'] + $refundMoney) ){
            throw new Exception('退款超出已付金额，总支付：'.$orderInfo['pay_prize'].'总已退:'.$orderInfo['refund_prize'].'本次申请退:'.$refundMoney );
        }
        if( $financeOrder ['money'] < ($financeOrder ['refund_money'] + $refundMoney) ){
            throw new Exception('退款超出当单金额');
        }
        //TODO更新到已退金额中
        $financeOrder ->refund_money = $financeOrder ['refund_money'] + $refundMoney;
        $financeOrder ->save();
        //生成一个退款单号
        return FinanceRefundService::newRefund($orderInfo['order_type'], $financeOrder["order_id"], $finance['money'], $refundMoney, $financeSn );
    }

}
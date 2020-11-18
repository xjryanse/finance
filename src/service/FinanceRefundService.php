<?php
namespace xjryanse\finance\service;

use xjryanse\logic\SnowFlake;
use xjryanse\order\service\OrderService;
/**
 * 退款
 */
class FinanceRefundService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceRefund';

    /**
     * 新订单写入
     * @param type $orderType   订单类型
     * @param type $orderSn     订单号
     * @param type $orderPrize  订单金额
     * @param type $refundPrize 退款金额
     * @param type $financeSn   支付单号
     * @return type
     */
    public static function newRefund( $orderType,$orderSn,$orderPrize,$refundPrize,$financeSn,$refundFrom = 'wechat')
    {
        $data['id']             = SnowFlake::generateParticle();
        $data['company_id']     = session('scopeCompanyId');
        $data['order_id']       = OrderService::snToId($orderSn);
        $data['order_type']     = $orderType;
        $data['refund_sn']      = 'REF'.$data['id'];
        $data['order_sn']       = $orderSn;
        $data['order_prize']    = $orderPrize;
        $data['refund_prize']   = $refundPrize;
        $data['finance_sn']     = $financeSn;
        $data['refund_from']    = $refundFrom;
        //获取收款单id
        $incomeId               = FinanceIncomeService::snToId( $financeSn );
        //获取支付单号
        $data['pay_sn']         = FinanceIncomePayService::incomeGetPaySn( $incomeId );
        
        return self::save($data);
    }    
    
    /**
     * 发起退款
     * @param type $orderId     订单号
     * @param type $financeSn   收款单号
     * @param type $refundMoney 退款金额
     */
    public static function refund( $orderId ,$financeSn,$refundMoney )
    {
        self::checkTransaction();
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
        return self::newRefund($orderInfo['order_type'], $financeOrder["order_id"], $finance['money'], $refundMoney, $financeSn );
    }
}

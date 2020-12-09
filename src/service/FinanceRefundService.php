<?php
namespace xjryanse\finance\service;

use xjryanse\logic\SnowFlake;
use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceIncomePayService;

/**
 * 退款
 */
class FinanceRefundService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceRefund';

    /*
     * 获取订单费用
     * @param type $orderId     订单id
     * @param type $status      收款状态，默认已完成
     */
    public static function getOrderMoney( $orderId ,$status = XJRYANSE_OP_FINISH )
    {
        $con[] = ['order_id','=',$orderId ];
        if( $status ){
            $con[] = [ 'refund_status', 'in', $status ];
        }
        $res = self::sum( $con, 'refund_prize' );
        //四舍五入
        return round( $res ,2);
    }    
    
    /**
     * 新订单写入
     * @param type $orderId     订单id
     * @param type $refundPrize 退款金额
     * @param type $paySn       支付单号
     * @param type $data        额外数据
     * @return type
     */
    public static function newRefund( $orderId, $refundPrize, $paySn, $data=[])
    {
        //【订单】
        $orderInfo = OrderService::getInstance( $orderId )->get();
        //订单类型
        $data['order_type'] = isset($orderInfo['order_type']) ? $orderInfo['order_type'] : '';
        //【支付单】
        $payInfo = FinanceIncomePayService::getBySn( $paySn );
        //订单类型
        $data['income_id'] = isset($payInfo['income_id']) ? $payInfo['income_id'] : '';
        $data['order_id']       = $orderId;     //订单id
        $data['refund_prize']   = $refundPrize; //退款金额
        $data['pay_sn']         = $paySn;       //支付单号
        //生成收款单
        $data['id']             = SnowFlake::generateParticle();
        $data['company_id']     = session('scopeCompanyId');
        $data['refund_sn']      = 'REF'.$data['id'];
        $data['refund_status']  = isset($data['refund_status']) ? $data['refund_status'] : XJRYANSE_OP_TODO;
        
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

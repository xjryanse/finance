<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceRefundService;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\finance\service\FinanceIncomeOrderService;
use xjryanse\order\service\OrderService;
use xjryanse\logic\SnowFlake;
use xjryanse\logic\DataCheck;
use Exception;

/**
 * 账户退款单表
 */
class FinanceRefundLogic
{
    /**
     * 创建新的退款单
     * @param type $data    
     * $data['']
     * @param type $prefix
     * @return type
     * @throws Exception
     */
    public static function newRefund( $data = [],$prefix='REF')
    {
        //校验事务
        FinanceRefundService::checkTransaction();

        $keys                       = ['refund_prize','order_id','pay_sn'];
        $notices['refund_prize']    = '退款金额必须';
        $notices['order_id']        = '订单id必须';
        $notices['pay_sn']          = '支付单号必须';
        DataCheck::must($data, $keys, $notices);
        //【订单】
        $orderInfo = OrderService::getInstance( $data['order_id'] )->get();
        //订单类型
        $data['order_type'] = isset($orderInfo['order_type']) ? $orderInfo['order_type'] : '';
        //【支付单】
        $payInfo = FinanceIncomePayService::getBySn( $data['pay_sn'] );
        //订单类型
        $data['income_id'] = isset($payInfo['income_id']) ? $payInfo['income_id'] : '';
        //生成收款单
        $data['id']             = SnowFlake::generateParticle();
        $data['refund_sn']      = $prefix . $data['id'];
        $data['refund_status']  = XJRYANSE_OP_TODO;
        $res = FinanceRefundService::save( $data );

        return $res;
    }
    
    /**
     * 取消收款单
     */
    public static function cancelRefund( $financeRefundId )
    {
        //校验事务
        FinanceRefundService::checkTransaction();
        //获取信息
        $info = FinanceRefundService::getInstance( $financeRefundId )->get(0);
        if( $info['refund_status'] != XJRYANSE_OP_TODO ){
            throw new Exception('退款单'.$financeRefundId.'非待付款状态，不可取消');
        }
        //删除退款单
        FinanceRefundService::getInstance( $financeRefundId )->delete();
        
        return true;
    }
    /**
     * 退款后入账
     * @param type $financeRefundId  退款单id
     */
    public static function afterRefundDoIncome( $financeRefundId )
    {
        //校验事务
        FinanceRefundService::checkTransaction();
        //财务支付入账记录
        $refundInfo = FinanceRefundService::getInstance( $financeRefundId )->get(0);
        if(!$refundInfo){
            throw new Exception('退款单'.$financeRefundId.'不存在');
        }
        //非已收款状态
        if( $refundInfo['refund_status'] != XJRYANSE_OP_FINISH ){
            throw new Exception( '退款单'. $financeRefundId .'非已退款状态' );
        }
        
        

        return true;
    }
}
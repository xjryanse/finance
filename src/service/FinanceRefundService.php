<?php
namespace xjryanse\finance\service;

use xjryanse\logic\SnowFlake;
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
        $data['order_id']       = $orderSn;
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
}

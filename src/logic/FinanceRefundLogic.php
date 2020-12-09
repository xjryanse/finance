<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceRefundService;
use xjryanse\wechat\service\WechatWxPayLogService;
use xjryanse\wechat\service\WechatWxPayConfigService;
use xjryanse\wechat\WxPay\DealWxPay;
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
    public static function newRefund( $data = [] )
    {
        //校验事务
        FinanceRefundService::checkTransaction();

        $keys                       = ['refund_prize','order_id','pay_sn'];
        $notices['refund_prize']    = '退款金额必须';
        $notices['order_id']        = '订单id必须';
        $notices['pay_sn']          = '支付单号必须';
        DataCheck::must($data, $keys, $notices);

        $res = FinanceRefundService::newRefund( $data['order_id'] , $data['refund_prize'], $data['pay_sn'], $data );
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
    /*
     * 执行退款操作
     * @param type $refundId    退款单id
     */
    public static function doRefund( $refundId )
    {
        $info = FinanceRefundService::getInstance( $refundId )->get( 0 );
        if($info['refund_status'] != XJRYANSE_OP_TODO){
            throw new Exception( '退款单'. $refundId .'非待退款状态');
        }
        //执行微信的退款操作
        if( $info['refund_from'] == 'wechat'){
            $wxPayLog   = WechatWxPayLogService :: getByOutTradeNo( $info['pay_sn'] );
            $param      = [];
            //退款金额
            $refundFee  = $wxPayLog['total_fee'];
            $param["out_refund_no"] = SnowFlake::generateParticle();    //退款单号
            $param["out_trade_no"]  = $wxPayLog['out_trade_no'];        //原支付订单号
            $param["total_fee"]     = $wxPayLog['total_fee'];           //订单总额（分）
            $param["refund_fee"]    = $refundFee ;                      //退款金额（分）
            //appid
            $configInfo = WechatWxPayConfigService::getByAppId( $wxPayLog['appid'] );
            //处理微信支付::退款
            $res = DealWxPay::doRefund($configInfo['id'], $param);
            if($res['result_code'] == 'SUCCESS'){
                FinanceRefundService::getInstance( $refundId )->update(['refund_status'=>XJRYANSE_OP_FINISH]);
            } else {
                throw new Exception( isset($res['err_code_des']) ? $res['err_code_des'] : '退款失败' );
            }
        }
    }
}
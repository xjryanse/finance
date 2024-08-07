<?php
namespace xjryanse\finance\logic;

use xjryanse\wechat\service\WechatWxPayLogService;
use xjryanse\wechat\service\WechatWxPayConfigService;
use xjryanse\finance\service\FinanceRefundService;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\system\service\SystemErrorLogService;
use xjryanse\user\service\UserService;
use xjryanse\logic\SnowFlake;
use xjryanse\logic\DataCheck;
use xjryanse\order\logic\OrderLogic;
use xjryanse\order\service\OrderService;
use xjryanse\user\logic\AccountLogic;
use xjryanse\wechat\WxPay\DealWxPay;
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
     * 取消退款单
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
        //退款超出已付，不让退了
        if(!OrderService::getInstance($info['order_id'])->refundCheck( $info['refund_prize'])){
            //不能退的情况，把退款单取消了
            throw new Exception( '退款超出已付金额');
        }
        //执行微信的退款操作
        if( $info['refund_from'] == FR_FINANCE_WECHAT){
            self::doRefundWechat($refundId);
        }
        //执行到余额的退款操作
        if( $info['refund_from'] == FR_FINANCE_MONEY ){
            self::doRefundMoney($refundId);
        }
    }
    
    /**
     * 退款单入账
     * @param type $refundId     退款单号
     * @param type $accountType         账户类型
     */
    public static function intoAccount( $refundId )
    {
        //校验事务
        FinanceRefundService::checkTransaction();        
        //获取退款单信息
        $refund = FinanceRefundService::getInstance( $refundId )->get(0);
        if($refund['into_account'] == 1 
                || FinanceAccountLogService::hasLog(FinanceRefundService::mainModel()->getTable(), $refundId)){
            throw new Exception('退款单'.$refundId.'已经入账过了');
        }
        if($refund['refund_status'] != XJRYANSE_OP_FINISH){
            throw new Exception('退款单'.$refundId.'未完成退款，不可入账');
        }
        
        //退款单入账
            $logData                    = [];
            $logData['reason']          = $refund['refund_reason'];
            $logData['from_table']      = FinanceRefundService::mainModel()->getTable();
            $logData['from_table_id']   = $refund['id'];
            $logData['user_id']         = $refund['creater'];   //TODO可能有bug：creater是否不一定是收款人？？
            //退款单入账:允许账户负值，避免入账失败
            FinanceAccountLogic::doOutcome($refund['company_id'], $refund['refund_from'], $refund['refund_prize'] ,$logData,true);
        //设定入账状态为已入账
        $data['into_account']           = 1;
        $data['into_account_user_id']   = session( SESSION_USER_ID );
        $data['into_account_user_name'] = UserService::getInstance( session( SESSION_USER_ID ) )->fRealname();
        $data['into_account_time']      = date('Y-m-d H:i:s');

        //总退款单更新为已入账
        FinanceRefundService::getInstance( $refundId )->update( $data );
    }
    /**
     * 执行微信退款操作
     */
    private static function doRefundWechat( $refundId )
    {
        $info       = FinanceRefundService::getInstance( $refundId )->get( 0 );
        $wxPayLog   = WechatWxPayLogService :: getByOutTradeNo( $info['pay_sn'] );
        //退款金额
        $refundFee  = (int) ($info['refund_prize'] * 100);
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
            //同步订单的收款金额信息
            OrderLogic::financeSync( $info['order_id'] );
            //退款后金额入账
            try{
                self::intoAccount( $refundId );
            } catch (\Exception $e){ 
                SystemErrorLogService::exceptionLog($e);
            }
        } else {
            throw new Exception( isset($res['err_code_des']) ? $res['err_code_des'] : '退款失败' );
        }        
    }
    /**
     * 执行余额退款操作
     */
    private static function doRefundMoney( $refundId )
    {
        $info                   = FinanceRefundService::getInstance( $refundId )->get( 0 );
        $payInfo                = FinanceIncomePayService::getBySn( $info['pay_sn'] );
        $data = [];
        $data['change_reason']  = $info['refund_reason'];
        //账户入账
        AccountLogic::doIncome( $payInfo['user_id'], YDZB_USER_ACCOUNT_TYPE_MONEY, $info['refund_prize'], $data );
        //更新为退款完成
        FinanceRefundService::getInstance( $refundId )->update(['refund_status'=>XJRYANSE_OP_FINISH]);
        //同步订单的收款金额信息
        OrderLogic::financeSync( $info['order_id'] );
        //退款后金额入账
        try{
            self::intoAccount( $refundId );
        } catch (\Exception $e){ 
            SystemErrorLogService::exceptionLog($e);
        }
    }
    
}
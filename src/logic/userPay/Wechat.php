<?php
namespace xjryanse\finance\logic\userPay;

use xjryanse\finance\interfaces\UserPayInterface;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\finance\logic\FinanceIncomePayLogic;
use xjryanse\finance\logic\FinanceIncomeLogic;
use xjryanse\wechat\service\WechatWxPayLogService;
use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use xjryanse\wechat\WxPay\WxPayLogic;

/**
 * 微信支付
 */
class Wechat extends Base implements UserPayInterface
{
    /**
     * 执行支付：
     * 先生成付款单
     * 微信支付，生成jsapi;
     * 余额支付，直接扣账
     * @param type $incomeId    收款单id
     */
    public static function pay( $statementId ,$thirdPayParam=[])
    {
        //必传参数
        DataCheck::must($thirdPayParam, ['wePubAppId','openid']);
        //校验必须
        $incomeInfo = FinanceStatementService::getInstance( $statementId )->get();
        if(!$incomeInfo){
            return false;
        }
        //生成支付单
        $data['order_id']   = Arrays::value($incomeInfo, 'order_id');
        $data['pay_by']     = FR_FINANCE_MONEY;
        //生成支付单
        $pay = FinanceIncomePayLogic::newPay($incomeInfo['id'], $incomeInfo['need_pay_prize'], $incomeInfo['user_id'], $data);
        //支付单
        $WxPayLogic         = new WxPayLogic($thirdPayParam['wePubAppId'], $thirdPayParam['openid'] );
        $attach             = ['statement_id'=>$incomeInfo['id']];  //收款单信息扔到附加数据
        // 20210519 改income_pay_sn 为income_id
        $wxPayJsApiOrder    = $WxPayLogic->getWxPayJsApiOrder($pay['income_id'], $incomeInfo['need_pay_prize'], $incomeInfo['statement_name'],json_encode($attach));    
        $wxPayJsApiOrder['pay_id'] = Arrays::value($pay, 'id');
        return $wxPayJsApiOrder;
    }
    
    /**
     * 付款完成后续处理
     * @param type $incomePayId 支付单id
     */
    public static function afterPay( $incomePayId )
    {
        $paySn      = FinanceIncomePayService::getInstance( $incomePayId )->fIncomePaySn();
        $info       = FinanceIncomePayService::getInstance( $incomePayId )->get();
        $wxPaySuccLog = WechatWxPayLogService::getByOutTradeNo($paySn);
        if( $wxPaySuccLog && $wxPaySuccLog['total_fee'] * 0.01 >= $info['money'] ){
            //支付单更新为已收款
            FinanceIncomePayLogic::afterPayDoIncome( $incomePayId );
            //收款单更新为已收款，且收款金额写入订单；
            FinanceIncomeLogic::afterPayDoIncome( $incomePayId );        
        }
        return $incomePayId;        
    }
    /**
     * 退款
     * @param type $statementId
     */
    public static function ref( $statementId ,$thirdPayParam=[])
    {
        //必传参数
        DataCheck::must($thirdPayParam, ['wePubAppId','openid']);
        
        $statementInfo      = FinanceStatementService::getInstance( $statementId )->get();
        $payStatementId     = Arrays::value($statementInfo, 'ref_statement_id');        
        $payStatementInfo   = FinanceStatementService::getInstance( $payStatementId )->get();
        if(!$payStatementInfo){
            throw new Exception('原支付单'.$payStatementId.'不存在');
        }
        $param["out_refund_no"] = Arrays::value($statementInfo, 'id');                   //退款单号
        $param["out_trade_no"]  = Arrays::value($statementInfo, 'ref_statement_id');     //原支付订单号
        $param["total_fee"]     = abs($payStatementInfo['need_pay_prize']) * 100;   //订单总额（分）
        $param["refund_fee"]    = abs($statementInfo['need_pay_prize']) * 100; //退款金额（分）

//        $param["out_trade_no"]  = 'PAY5206678365661663232';     //原支付订单号
        $WxPayLogic         = new WxPayLogic($thirdPayParam['wePubAppId'], $thirdPayParam['openid'] );

        $res = $WxPayLogic->doRefund( $param );
        return $res;
    }
}

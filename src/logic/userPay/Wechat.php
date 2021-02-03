<?php
namespace xjryanse\finance\logic\userPay;

use xjryanse\finance\interfaces\UserPayInterface;
use xjryanse\finance\service\FinanceIncomeService;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\finance\logic\FinanceIncomePayLogic;
use xjryanse\finance\logic\FinanceIncomeLogic;
use xjryanse\wechat\service\WechatWxPayLogService;
use xjryanse\logic\DataCheck;
use app\webapi\logic\pay\WxPayLogic;

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
    public static function pay( $incomeId ,$thirdPayParam=[])
    {
        //必传参数
        DataCheck::must($thirdPayParam, ['wePubAppId','openid']);
        //校验必须
        $incomeInfo = FinanceIncomeService::getInstance( $incomeId )->get(0);
        if(!$incomeInfo){
            return false;
        }
        //生成支付单
        $data['order_id']   = Arrays::value($incomeInfo, 'order_id');
        $data['pay_by']     = FR_FINANCE_MONEY;
        //生成支付单
        $pay = FinanceIncomePayLogic::newPay($incomeInfo['id'], $incomeInfo['money'], $incomeInfo['pay_user_id'], $data);
        //支付单
        $WxPayLogic         = new WxPayLogic($thirdPayParam['wePubAppId'], $thirdPayParam['openid'] );
        $attach             = ['finance_id'=>$incomeInfo['id']];  //收款单信息扔到附加数据
        $wxPayJsApiOrder    = $WxPayLogic->getWxPayJsApiOrder($pay['income_pay_sn'], $incomeInfo['money'], '店铺升级',json_encode($attach));        
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
}

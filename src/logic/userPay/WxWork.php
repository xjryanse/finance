<?php
namespace xjryanse\finance\logic\userPay;

use xjryanse\finance\interfaces\UserPayInterface;
use app\index\controller\WeWork;
use xjryanse\wechat\WxPay\lib\WxPayApi;
use xjryanse\wechat\WxPay\lib\WxPayJsApiPay;
use xjryanse\logic\Arrays;
/**
 * 企业微信支付
 */
class WxWork extends Base implements UserPayInterface
{
    /**
     * 执行支付：
     * 先生成付款单
     * 微信支付，生成jsapi;
     * 余额支付，直接扣账
     * @param type $incomeId    收款单id
     */
    public static function pay( $statementId , $money,$thirdPayParam=[])
    {
        $prePay = WeWork::createOrder($statementId , $money,$thirdPayParam);
        
        // $res = WeWork::getPaySign($prePay['prepay_id'] , $thirdPayParam);
        
        
        $jsapi = new WxPayJsApiPay();
        $jsapi->SetAppid(Arrays::value($thirdPayParam,'weAppId'));
        $timeStamp = time();
        $jsapi->SetTimeStamp($timeStamp);
        $jsapi->SetNonceStr(WxPayApi::getNonceStr());
        $jsapi->SetPackage("prepay_id=" . $prePay['prepay_id']);

        $config = $this->config;
        $jsapi->SetPaySign($jsapi->MakeSign($config));
        return $jsapi->GetValues();
        
        return $res;
        
//        //未结金额
//        $remainMoney            = FinanceStatementService::getInstance($statementId)->remainMoney();
//        if($money > abs($remainMoney)){
//            throw new Exception('支付金额异常'.$money.'-'.abs($remainMoney));
//        }
//        //必传参数
//        DataCheck::must($thirdPayParam, ['wePubAppId','openid']);
//        //校验必须
//        $incomeInfo = FinanceStatementService::getInstance( $statementId )->get();
//        if(!$incomeInfo){
//            return false;
//        }
//        //生成支付单
//        $data['order_id']   = Arrays::value($incomeInfo, 'order_id');
//        $data['pay_by']     = FR_FINANCE_MONEY;
//        //生成支付单
//        Db::startTrans();
//        $pay = FinanceIncomePayLogic::newPay($incomeInfo['id'], $money, $incomeInfo['user_id'], $data);
//        Db::commit();
//        //支付单
//        $WxPayLogic         = new WxPayLogic($thirdPayParam['wePubAppId'], $thirdPayParam['openid'] );
//        $attach             = ['statement_id'=>$incomeInfo['id']];  //收款单信息扔到附加数据
//        // 20210519 改income_pay_sn 为income_id
//        $wxPayJsApiOrder    = $WxPayLogic->getWxPayJsApiOrder($pay['income_id'], $money, $incomeInfo['statement_name'],json_encode($attach));    
//        $wxPayJsApiOrder['pay_id'] = Arrays::value($pay, 'id');
//        // 20230530:未支付成功时，前端有取消订单的动作。
//        $wxPayJsApiOrder['order_id'] = Arrays::value($data, 'order_id');
//        return $wxPayJsApiOrder;
    }
    
    /**
     * 付款完成后续处理（好像没用）
     * @param type $incomePayId 支付单id
     */
    public static function afterPay( $incomePayId )
    {

    }
    /**
     * 退款
     * @param type $statementId
     */
    public static function ref( $statementId ,$thirdPayParam=[])
    {

    }
    
    public static function secCollect( $statementId ,$thirdPayParam=[])
    {

    }
    
    /**
     * 20230904:单笔关单
     * @param type $statementId
     */
    public static function cancel($statementId){
        // 关单
        
        return true;
    }

}

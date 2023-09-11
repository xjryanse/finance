<?php
namespace xjryanse\finance\logic\userPay;

use xjryanse\finance\interfaces\UserPayInterface;
use xjryanse\finance\service\FinanceStatementService;
use app\thirdpay\logic\CmbSktLogic;
use xjryanse\logic\Arrays;
/**
 * 招商银行收款通
 */
class CmbSkt extends Base implements UserPayInterface
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
        $stInfo = FinanceStatementService::getInstance($statementId)->get();
        $cmbSktLogic = new CmbSktLogic();
        // 20230904：改成传账单
        // $pData['payNo']     = session(SESSION_USER_ID);
        $pData['payNo']     = $statementId;
        $res = $cmbSktLogic->addSingleBill($statementId,$money, $pData);
        // 捷算通appid
        $data['appId']      = $cmbSktLogic->getJsWxappid();
        // 捷算通path
        $data['path']       = 'pages/index/index';

        $data['merchId']    = $cmbSktLogic->getJsMchId();
        $data['payNo']      = $pData['payNo'];
        $data['navigationBarTitle']    = Arrays::value($stInfo, 'statement_name');
        
        $res['sktParam'] = $data;
        return $res;
    }
    
    /**
     * 付款完成后续处理（好像没用）
     * @param type $incomePayId 支付单id
     */
    public static function afterPay( $incomePayId )
    {
//        // $paySn      = FinanceIncomePayService::getInstance( $incomePayId )->fIncomePaySn();
//        $info       = FinanceIncomePayService::getInstance( $incomePayId )->get();
//        $wxPaySuccLog = WechatWxPayLogService::getByOutTradeNo($info['income_id']);
//        Debug::debug('$wxPaySuccLog',$wxPaySuccLog);
//        if( $wxPaySuccLog && $wxPaySuccLog['total_fee'] * 0.01 >= $info['money'] ){
//            //支付单更新为已收款
//            FinanceIncomePayLogic::afterPayDoIncome( $incomePayId );
//            //收款单更新为已收款，且收款金额写入订单；
//            FinanceIncomeLogic::afterPayDoIncome( $incomePayId );        
//        }
//        return $incomePayId;        
    }
    /**
     * 退款
     * @param type $statementId
     */
    public static function ref( $statementId ,$thirdPayParam=[])
    {
        // 退款账单信息
        $stInfo = FinanceStatementService::getInstance($statementId)->get();
        // 原单号
        $rawStatementId = Arrays::value($stInfo, 'ref_statement_id');

        $cmbSktLogic    = new CmbSktLogic();
        return $cmbSktLogic->refundMoney($statementId,abs($stInfo['need_pay_prize']), $rawStatementId);
    }
    
    public static function secCollect( $statementId ,$thirdPayParam=[])
    {
//        //①取账单信息和公众号信息
//        $statementInfo = FinanceStatementService::getInstance($statementId)->get();
//        $companyId  = Arrays::value($statementInfo, 'company_id');
//        $wePubId    = SystemCompanyService::getInstance($companyId)->fWePubId();
//        $wePubInfo  = WechatWePubService::getInstance( $wePubId )->get();
//        //②取账单对应的粉丝信息
//        $cond   = [];
//        $cond[] = ['user_id','=',Arrays::value($statementInfo, 'user_id')];
//        $WechatWePubFansUserInfo    = WechatWePubFansUserService::mainModel()->where($cond)->find();
//        if(!$WechatWePubFansUserInfo){
//            throw new Exception('未找到用户'.Arrays::value($statementInfo, 'user_id').'绑定的微信公众号粉丝信息');
//        }
//        //③相同 订单id，取买方账单。
//        $con    = [];
//        $con[]  = ['order_id','=',Arrays::value($statementInfo, 'order_id')];
//        $con[]  = ['statement_cate','=','buyer'];
//        $con[]  = ['change_type','=',1];
//        $buyerStatementInfo = FinanceStatementService::find( $con );
//        if(!$buyerStatementInfo){
//            throw new Exception('未找到订单'.Arrays::value($statementInfo, 'order_id').'对应的买方账单');
//        }
//        //④买方账单到微信支付表取支付流水，拿到 transaction_id；
//        $conPay     = [];
//        $conPay[]   = ['statement_id','=',$buyerStatementInfo['id']];
//        $wxPayLogInfo = WechatWxPayLogService::mainModel()->where( $conPay )->find();
//        if(!$wxPayLogInfo){
//            throw new Exception('账单'.$buyerStatementInfo['id'].'没有微信支付流水');
//        }
//        //⑤组装数据
//        $wePubAppId = Arrays::value($wePubInfo, 'appid');
//        $openid     = Arrays::value($WechatWePubFansUserInfo, 'openid');
//        $WxPayLogic = new WxPayLogic( $wePubAppId, $openid );
//        
//        $input['transaction_id']    = Arrays::value($wxPayLogInfo, 'transaction_id');
//        $input['out_order_no']      = $statementId;
//        $receivers[] = [
//            "type"          =>"PERSONAL_OPENID",
//            "account"       =>$openid,
//            "amount"        =>intval(abs(Arrays::value($statementInfo, 'need_pay_prize') * 100)),
//            "description"   =>Arrays::value($statementInfo, 'statement_name'),
//        ];
//        //执行分账
//        $res = $WxPayLogic->secProfitSharing( $input, $receivers );
//        return $res;
    }
    /**
     * 20230904:单笔关单
     * @param type $statementId
     */
    public static function cancel($statementId){
        $cmbSktLogic = new CmbSktLogic();
        $res = $cmbSktLogic->deleteBill($statementId);

        return $res;
    }
}

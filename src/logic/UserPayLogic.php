<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\logic\Arrays;
/**
 * 用户支付逻辑
 */
class UserPayLogic
{
    protected static $baseNamespace = '\\xjryanse\\finance\\logic\\userPay\\';
    
    /**
     * 收款单id进行支付操作
     * @param type $incomeId        收款单id
     * @param type $payBy           用啥支付
     * @param type $thirdPayParam   用于传第三方支付所需参数
     * @return type
     */
    public static function pay( $incomeId , $money , $payBy ,$thirdPayParam = [])
    {
        //动态执行各支付方式的映射类库
        //20210924，增加$money兼容组合支付
        $res = call_user_func([self::$baseNamespace. ucfirst($payBy), 'pay'],$incomeId, $money , $thirdPayParam);
        return $res;
    }
    /**
     * 收款单id进行支付操作
     * @param type $incomeId        收款单id
     * @param type $payBy           用啥支付
     * @param type $thirdPayParam   用于传第三方支付所需参数
     * @return type
     */
    public static function afterPay( $incomePayId )
    {
        $payBy = FinanceIncomePayService::getInstance( $incomePayId )->fPayBy();
        //动态执行各支付方式的映射类库
        $res = call_user_func([self::$baseNamespace. ucfirst($payBy), 'afterPay'] , $incomePayId );
        return $res;        
    }
    
    /**
     * 收款单id进行退款
     * @param type $statementId     收款单id
     * @param type $payBy           用啥支付
     * @param type $thirdPayParam   用于传第三方支付所需参数
     * @return type
     */
    public static function ref( $statementId , $payBy ,$thirdPayParam = [])
    {
        //动态执行各支付方式的映射类库
        $res = call_user_func([self::$baseNamespace. ucfirst($payBy), 'ref'],$statementId, $thirdPayParam);
        return $res;
    }
    
    /**
     * 账单id进行收款操作
     * @param type $statementId     收款单id
     * @param type $payBy           用啥支付
     * @param type $thirdPayParam   用于传第三方支付所需参数
     * @return type
     */
    public static function collect( $statementId , $payBy ,$thirdPayParam = [])
    {
        //动态执行各支付方式的映射类库
        $res = call_user_func( [self::$baseNamespace. ucfirst( $payBy ), 'collect'] , $statementId, $thirdPayParam );
        return $res;
    }
    /**
     * 用户分账收款（目前只支持微信）
     */
    public static function secCollect ( $statementId , $payBy ,$thirdPayParam = []){
        //动态执行各支付方式的映射类库
        $res = call_user_func([self::$baseNamespace. ucfirst($payBy), 'secCollect'],$statementId, $thirdPayParam);
        return $res;
    }
    
    
    /**
     * 收款单id进行支付操作
     * @param type $incomeId        收款单id
     * @param type $payBy           用啥支付
     * @param type $thirdPayParam   用于传第三方支付所需参数
     * @return type
     */
    public static function cancel( $statementId )
    {
        $info   = FinanceStatementService::getInstance($statementId)->get();
        $payBy  = Arrays::value($info, 'pay_by');
        if(!$payBy){
            return true;
        }
        //动态执行各支付方式的映射类库
        //20210924，增加$money兼容组合支付
        $res = call_user_func([self::$baseNamespace. ucfirst($payBy), 'cancel'], $statementId);
        return $res;
    }
}

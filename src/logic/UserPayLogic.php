<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceIncomePayService;
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
    public static function pay( $incomeId , $payBy ,$thirdPayParam = [])
    {
        //动态执行各支付方式的映射类库
        $res = call_user_func([self::$baseNamespace. ucfirst($payBy), 'pay'],$incomeId, $thirdPayParam);
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
     * 收款单id进行支付操作
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
}

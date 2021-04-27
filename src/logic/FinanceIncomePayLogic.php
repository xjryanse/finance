<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\finance\service\FinanceStatementService;
use Exception;
/**
 * 支付单逻辑
 */
class FinanceIncomePayLogic
{
    /**
     * 创建新的支付单
     * @param type $incomeId    支付单id
     * @param type $money       金额
     * @param type $userId      支付用户id
     * @param type $data        数据
     * @return type
     */
    public static function newPay( $incomeId, $money, $userId = '',$data = [])
    {
        //校验事务
        FinanceIncomePayService::checkTransaction();
        //收款单信息
        $incomeInfo     = FinanceStatementService::getInstance( $incomeId )->get(0);
        if( !$incomeInfo ){
            throw new Exception( '收款单'.$incomeId.'不存在' );
        }
        if( $incomeInfo['need_pay_prize'] != $money ){
            throw new Exception( '收款单'.$incomeId.'金额'.$incomeInfo['need_pay_prize'].'与申请支付金额'.$money .'不匹配');
        }
        if( $incomeInfo['has_settle'] != 0 ){
            throw new Exception( '收款单' . $incomeId .'非待结算状态');
        }
        //支付单号
        $data['id']             = FinanceIncomePayService::mainModel()->newId();
        $data['income_pay_sn']  = "PAY".$data['id'];
        $data['income_id']      = $incomeId;
        $data['user_id']        = $userId;
        $data['money']          = $money;
        $data['income_status']  = XJRYANSE_OP_TODO;
        $res = FinanceIncomePayService::save( $data );
        return $res;
    }
    
    /**
     * 取消支付单
     */
    public static function cancelPay( $financeIncomePayId )
    {
        return FinanceIncomePayService::getInstance( $financeIncomePayId )->cancelPay();
    }
    /**
     * 支付后入账
     * @param type $financeIncomePayId  支付单id
     */
    public static function afterPayDoIncome( $financeIncomePayId )
    {
        return FinanceIncomePayService::getInstance( $financeIncomePayId )->afterPayDoIncome();
    }
}
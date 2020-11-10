<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceIncomeService;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\logic\SnowFlake;
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
        $incomeInfo     = FinanceIncomeService::getInstance( $incomeId )->get();
        if( !$incomeInfo ){
            throw new Exception( '收款单'.$incomeId.'不存在' );
        }
        if( $incomeInfo['money'] != $money ){
            throw new Exception( '收款单'.$incomeId.'金额'.$incomeInfo['money'].'与申请支付金额'.$money .'不匹配');
        }
        if( $incomeInfo['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception( '收款单' . $incomeId .'非待收款状态');
        }
        //支付单号
        $data['id']             = SnowFlake::generateParticle();
        $data['income_pay_sn']  = "PAY".$data['id'];
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
        //校验事务
        FinanceIncomePayService::checkTransaction();
        //支付单关闭
        $financeIncomePay = FinanceIncomePayService::getInstance( $financeIncomePayId )->get();
        if( !$financeIncomePay ){
            throw new Exception( '支付单'.$financeIncomePayId.'不存在' );
        }
        if( $financeIncomePay['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception( '支付单'.$financeIncomePayId.'非待收款状态不能操作' );
        }
        //支付单更新为已关闭状态
        $res = FinanceIncomePayService::getInstance( $financeIncomePayId )->setFieldWithPreValCheck('income_status',XJRYANSE_OP_TODO,XJRYANSE_OP_CLOSE );
        return $res;
    }
    /**
     * 支付后入账
     * @param type $financeIncomePayId  支付单id
     */
    public static function afterPayDoIncome( $financeIncomePayId )
    {
        //校验事务
        FinanceIncomePayService::checkTransaction();
        //支付单关闭
        $financeIncomePay = FinanceIncomePayService::getInstance( $financeIncomePayId )->get();        
        if( !$financeIncomePay ){
            throw new Exception( '支付单'.$financeIncomePayId.'不存在' );
        }
        if( $financeIncomePay['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception( '支付单'.$financeIncomePayId.'非待收款状态不能操作' );
        }
        //支付单更新为已完成
        $res = FinanceIncomePayService::getInstance( $financeIncomePayId )->setFieldWithPreValCheck('income_status',XJRYANSE_OP_TODO,XJRYANSE_OP_FINISH );
        return $res;
    }
}
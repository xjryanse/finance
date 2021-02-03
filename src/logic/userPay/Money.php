<?php
namespace xjryanse\finance\logic\userPay;

use xjryanse\finance\interfaces\UserPayInterface;
use xjryanse\finance\service\FinanceIncomeService;
use xjryanse\user\logic\AccountLogic;
use xjryanse\finance\logic\FinanceIncomeLogic;
use xjryanse\finance\logic\FinanceIncomePayLogic;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\user\service\UserAccountLogService;
use xjryanse\logic\Arrays;
/**
 * 余额支付逻辑
 */
class Money extends Base implements UserPayInterface
{
    /**
     * 执行支付：
     * 先生成付款单
     * 微信支付，生成jsapi;
     * 余额支付，直接扣账
     * @param type $incomeId        收款单id
     * @param type $thirdPayParam   第三方支付参数
     */
    public static function pay( $incomeId  ,$thirdPayParam = [])
    {
        //校验必须
        $incomeInfo = FinanceIncomeService::getInstance( $incomeId )->get(0);
        if(!$incomeInfo){
            return false;
        }
        //生成支付单
        $data['order_id']   = Arrays::value($incomeInfo, 'order_id');
        $data['pay_by']     = FR_FINANCE_MONEY;
        $pay = FinanceIncomePayLogic::newPay($incomeInfo['id'], $incomeInfo['money'], $incomeInfo['pay_user_id'], $data );
        //记录数据
        $data['from_table'] = FinanceIncomePayService::mainModel()->getTable();
        $data['from_table_id'] = $pay['id'];
        //扣减账户余额
        $resp = AccountLogic::doOutcome( $incomeInfo['pay_user_id'] , YDZB_USER_ACCOUNT_TYPE_MONEY, $incomeInfo['money'], $data ); 
        //***********【余额支付，支付完直接执行后续处理】***************//
        self::afterPay( $pay['id'] );
        return $resp;
    }
    
    /**
     * 付款完成后续处理
     * @param type $incomePayId 支付单id
     */
    public static function afterPay( $incomePayId )
    {
        $fromTable  = FinanceIncomePayService::mainModel()->getTable();
        $payLog     = UserAccountLogService::hasLog( $fromTable, $incomePayId );
        $info       = FinanceIncomePayService::getInstance( $incomePayId )->get();
        //支出为负值，故取绝对值
        if( $payLog && abs($payLog['change']) >= abs($info['money'])){
            //支付单更新为已收款
            FinanceIncomePayLogic::afterPayDoIncome( $incomePayId );
            //收款单更新为已收款，且收款金额写入订单；
            FinanceIncomeLogic::afterPayDoIncome( $incomePayId );        
        }
        return $incomePayId;
    }

}

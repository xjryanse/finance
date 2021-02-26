<?php
namespace xjryanse\finance\logic\userPay;

use xjryanse\finance\interfaces\UserPayInterface;
use xjryanse\finance\service\FinanceIncomeService;
use xjryanse\user\logic\AccountLogic;
use xjryanse\finance\logic\FinanceIncomeLogic;
use xjryanse\finance\logic\FinanceIncomePayLogic;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\user\service\UserAccountLogService;
use xjryanse\finance\service\FinanceAccountService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceAccountLogService;
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
     * @param type $statementId     对账单id
     * @param type $thirdPayParam   第三方支付参数
     */
    public static function pay( $statementId  ,$thirdPayParam = [])
    {
        $info                   = FinanceStatementService::getInstance( $statementId )->get();
        $companyId              = Arrays::value($info, 'company_id');
        $data['user_id']        = Arrays::value($info, 'user_id');
        $data['customer_id']    = Arrays::value($info, 'customer_id');
        $data['money']          = Arrays::value($info, 'need_pay_prize');
        $data['statement_id']   = $statementId;
        $data['change_type']    = Arrays::value($info, 'change_type');
        $data['account_id']     = FinanceAccountService::getIdByAccountType($companyId, FR_FINANCE_MONEY );
        //公司账户进账
        FinanceAccountLogService::save($data);
        //扣减账户余额
        $resp = AccountLogic::doOutcome( $data['user_id'] , FR_FINANCE_MONEY, $data['money'], $data ); 
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

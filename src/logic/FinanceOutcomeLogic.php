<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceOutcomeService;
use xjryanse\finance\service\FinanceOutcomeOrderService;
use xjryanse\finance\service\FinanceOutcomePayService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\user\service\UserService;
use xjryanse\order\logic\OrderLogic;
use xjryanse\logic\SnowFlake;
use xjryanse\system\service\SystemErrorLogService;
use Exception;

/**
 * 账户付款单表
 */
class FinanceOutcomeLogic
{
    /**
     * 创建新的付款单
     */
    public static function newOutcome( $data = [],$prefix='FOU')
    {
        //校验事务
        FinanceOutcomeService::checkTransaction();
        //生成付款单id和订单号
        $data['id']             = SnowFlake::generateParticle();
        $data['outcome_sn']      = $prefix . $data['id'];
        $data['outcome_status']  = XJRYANSE_OP_TODO;
        $res = FinanceOutcomeService::save($data);
        //付款单对应的订单信息存储
        if(isset($data['orders'])){
            foreach( $data['orders'] as $v ){
                $tmp = $v;
                $tmp['outcome_id']       = $res['id'];
                $tmp['outcome_status']   = XJRYANSE_OP_TODO;
                if(!isset($tmp['order_id']) || !$tmp['order_id']){
                    throw new Exception('orders数组中，order_id必须');
                }
                //有传就行，特殊情况可以为0；
                if(!isset($tmp['money'])){
                    throw new Exception('orders数组中，money必须');
                }
                //保存单条数据，TODO批量
                FinanceOutcomeOrderService::save($tmp);
            }
        }

        return $res;
    }
    
    /**
     * 取消付款单
     */
    public static function cancelOutcome( $financeOutcomeId )
    {
        //校验事务
        FinanceOutcomeService::checkTransaction();
        //获取信息
        $info = FinanceOutcomeService::getInstance( $financeOutcomeId )->get(0);
        if( $info['outcome_status'] != XJRYANSE_OP_TODO ){
            throw new Exception('付款单'.$financeOutcomeId.'非待付款状态，不可取消');
        }

        //删除付款款单：关联删除支付单，付款单订单
        FinanceOutcomeService::getInstance( $financeOutcomeId )->delete();
        
        return true;
    }
    /**
     * 支付后入账
     * @param type $financeOutcomePayId  支付单id
     */
    public static function afterPayDoIncome( $financeOutcomePayId )
    {
        //校验事务
        FinanceOutcomeService::checkTransaction();
        //财务支付入账记录
        $financeOutcomePay = FinanceOutcomePayService::getInstance( $financeOutcomePayId )->get(0);
        if(!$financeOutcomePay){
            throw new Exception('支付单'.$financeOutcomePayId.'不存在');
        }
        //非已收款状态
        if( $financeOutcomePay['outcome_status'] != XJRYANSE_OP_FINISH ){
            throw new Exception( '支付单'. $financeOutcomePayId .'非已收款状态' );
        }
        //付款单信息
        $financeOutcome = FinanceOutcomeService::getInstance( $financeOutcomePay['outcome_id'] )->get(0);
        //金额匹配校验
        if( $financeOutcomePay['money'] !=  $financeOutcome['money'] ){
            throw new Exception( '支付单'. $financeOutcomePayId .'与付款单'. $financeOutcome['id'] . '金额不匹配' );
        }
        //更新付款单状态:待支付为已支付
        FinanceOutcomeService::getInstance( $financeOutcomePay['outcome_id'])->setFieldWithPreValCheck( 'outcome_status',XJRYANSE_OP_TODO,XJRYANSE_OP_FINISH );
        //付款单关联订单的信息
        $con1[] = ['outcome_id','=',$financeOutcomePay['outcome_id']];
        $financeOutcomeOrders = FinanceOutcomeOrderService::lists( $con1 );
        foreach( $financeOutcomeOrders as $outcomeOrder ){
            //更新收款订单状态：待支付为已支付
            FinanceOutcomeOrderService::getInstance( $outcomeOrder['id'])->setFieldWithPreValCheck( 'outcome_status',XJRYANSE_OP_TODO,XJRYANSE_OP_FINISH );
            //同步订单的收款金额信息
            OrderLogic::financeSync( $outcomeOrder['order_id'] );
        }
        //尝试执行入账操作
        try{
            self::intoAccount( $financeOutcomePay['outcome_id'] );
        } catch (\Exception $e){ 
            SystemErrorLogService::exceptionLog($e);
        }
        return true;
    }
    /**
     * 支付单入账
     * @param type $financeOutcomeId     付款单号
     * @param type $accountType         账户类型
     */
    public static function intoAccount( $financeOutcomeId )
    {
        //校验事务
        FinanceOutcomeService::checkTransaction();        
        //获取付款单信息
        $financeOutcome = FinanceOutcomeService::getInstance( $financeOutcomeId )->get(0);
        if($financeOutcome['into_account'] == 1
                || FinanceAccountLogService::hasLog( FinanceOutcomeService::mainModel()->getTable(), $financeOutcomeId )
            ){
            throw new Exception('付款单'.$financeOutcomeId.'已经入账过了');
        }
        //获取支付单信息
        $con[] = [ 'outcome_id'      ,'=',$financeOutcomeId ];
        $con[] = [ 'outcome_status'  ,'=',XJRYANSE_OP_FINISH ];
        $con[] = [ 'into_account'   ,'=',0 ];
        $outcomePaysMoney    = FinanceOutcomePayService::sum( $con ,'money');
        if( $outcomePaysMoney < $financeOutcome['money']){
            throw new Exception( '付款单'.$financeOutcomeId.'未入账金额小于应收款金额，可能未完全收款，不能入账' );
        }
        $outcomePays         = FinanceOutcomePayService::lists( $con );
        foreach( $outcomePays as $outcomePay){
            $logData    = [];
            $logData['reason']          = $outcomePay['describe'];
            $logData['from_table']      = FinanceOutcomePayService::mainModel()->getTable();
            $logData['from_table_id']   = $outcomePay['id'];
            $logData['user_id']         = $outcomePay['user_id'];            
            //付款单入账
            FinanceAccountLogic::doOutcome($outcomePay['company_id'], $outcomePay['pay_by'], $outcomePay['money'] ,$logData);
            //设定入账状态为已入账
            FinanceOutcomePayService::getInstance( $outcomePay['id'] )->setField( 'into_account',1 );
        }

        $data['into_account']           = 1;
        $data['into_account_user_id']   = session( SESSION_USER_ID );
        $data['into_account_user_name'] = UserService::getInstance( session( SESSION_USER_ID ) )->fRealname();
        $data['into_account_time']      = date('Y-m-d H:i:s');

        //总付款单更新为已入账
        FinanceOutcomeService::getInstance( $financeOutcomeId )->update( $data );
    }
}
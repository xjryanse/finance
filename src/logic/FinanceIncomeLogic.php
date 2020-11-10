<?php
namespace xjryanse\finance\logic;

use xjryanse\finance\service\FinanceIncomeService;
use xjryanse\finance\service\FinanceIncomeOrderService;
use xjryanse\finance\service\FinanceIncomePayService;
use xjryanse\logic\SnowFlake;
use Exception;

/**
 * 账户收款单表
 */
class FinanceIncomeLogic
{
    /**
     * 创建新的收款单
     */
    public static function newIncome( $data = [],$prefix='FIN')
    {
        //校验事务
        FinanceIncomeService::checkTransaction();
        //生成收款单id和订单号
        $data['id']         = SnowFlake::generateParticle();
        $data['income_sn']  = $prefix . $data['id'];
        $res = FinanceIncomeService::save($data);
        //收款单对应的订单信息存储
        if(isset($data['orders'])){
            foreach($data['orders'] as $v){
                $tmp = $v;
                $tmp['income_id'] = $res['id'];
                //保存单条数据，TODO批量
                FinanceIncomeOrderService::save($tmp);
            }
        }

        return $res;
    }
    
    /**
     * 取消收款单
     */
    public function cancelIncome( $financeIncomeId )
    {
        //校验事务
        FinanceIncomeService::checkTransaction();
        //获取信息
        $info = FinanceIncomeService::getInstance( $financeIncomeId )->get();
        if( $info['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception('收款单'.$financeIncomeId.'非待付款状态，不可取消');
        }
        $con[] = [ 'income_id', '=', $financeIncomeId ]; 
        //删除收款单的对应订单
        FinanceIncomeOrderService::mainModel()->where( $con )->delete();
        //删除收款单
        FinanceIncomeService::getInstance( $financeIncomeId )->delete();
        return true;
    }
    /**
     * 支付后入账
     * @param type $financeIncomePayId  支付单id
     */
    public function afterPayDoIncome( $financeIncomePayId )
    {
        //校验事务
        FinanceIncomeService::checkTransaction();
        //财务支付入账记录
        $financeIncomePay = FinanceIncomePayService::getInstance( $financeIncomePayId )->get();
        if(!$financeIncomePay){
            throw new Exception('支付单'.$financeIncomePayId.'不存在');
        }
        //非已收款状态
        if( $financeIncomePay['income_status'] != XJRYANSE_OP_FINISH ){
            throw new Exception( '支付单'. $financeIncomePayId .'非已收款状态' );
        }
        //收款单信息
        $financeIncome = FinanceIncomeService::getInstance( $financeIncomePay['income_id'] )->get();
        //金额匹配校验
        if( $financeIncomePay['money'] !=  $financeIncome['money'] ){
            throw new Exception( '支付单'. $financeIncomePayId .'与收款单'. $financeIncome['id'] . '金额不匹配' );
        }
        //更新收款单状态:待支付为已支付
        FinanceIncomeService::getInstance( $financeIncomePay['income_id'])->setFieldWithPreValCheck( 'income_status',XJRYANSE_OP_TODO,XJRYANSE_OP_FINISH );
        //收款单关联订单的信息
        $con1[] = ['income_id','=',$financeIncomePay['income_id']];
        $financeIncomeOrders = FinanceIncomeOrderService::lists( $con1 );
        foreach( $financeIncomeOrders as $incomeOrder ){
            //更新收款订单状态：待支付为已支付
            FinanceIncomeOrderService::getInstance( $incomeOrder['id'])->setFieldWithPreValCheck( 'income_status',XJRYANSE_OP_TODO,XJRYANSE_OP_FINISH );
        }
        return true;
    }
}
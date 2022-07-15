<?php
namespace xjryanse\finance\logic;

use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\logic\Debug;
use xjryanse\logic\Arrays;

/**
 * 多个收付款步骤合成一个的逻辑；
 * ①写入账单明细；
 * ②生成账单；
 * ③记录账款信息
 */
class PackLogic
{
    /**
     * 20220609 后台创建订单收款单，并记录金额信息
     * @param type $orderId         订单id
     * @param type $accountId       收款账户id
     * @param type $money           收款金额
     * @param type $stOrData        额外数据
     * @param string $prizeKey      价格key
     */
    public static function financeIncomeAdm($orderId,$accountId,$money, $stOrData=[], $prizeKey = "GoodsPrize" ){
        OrderService::checkTransaction();
        //步骤①创建一笔收款明细
        $statementOrderInfo = FinanceStatementOrderService::prizeKeySave($prizeKey, $orderId, $money,$stOrData);
        //步骤②生成账单
        $data['statementOrderIds']  = [$statementOrderInfo['id']];
        $data['has_confirm']        = 1;
        $statementInfo              = FinanceStatementService::save( $data );
        //步骤③收款入账
        $accountData['account_id']      =  $accountId;
        $accountData['statement_id']    =  $statementInfo['id'];
        $res = FinanceAccountLogService::save($accountData);
        //更新订单的费用信息（多退少补）；
        $orderInfo  = OrderService::getInstance( $orderId )->get();
        FinanceStatementOrderService::updateOrderMoney($orderId, $orderInfo['order_prize']);
        //20220615：TODO逻辑过于复杂，求简化
        if($orderInfo && $orderInfo['pre_order_id']){
            $buyerPrize = FinanceStatementOrderService::getBuyerPrize($orderId);
            Debug::debug('$buyerPrize',$buyerPrize);
            FinanceStatementOrderService::updateNeedOutcomePrize($orderInfo['pre_order_id'], $buyerPrize);
        }
        
        return $res;
    }
    /**
     * 20220620内存
     * @param type $orderId
     * @param type $accountId
     * @param type $money
     * @param type $stOrData
     * @param type $prizeKey
     * @param type $statementOrderId    对账单id，用于关联获取客户信息（付款存在多客户情况）
     * @return type
     */
    public static function financeDealRam($orderId,$accountId,$money, $stOrData=[], $prizeKey='' ,$statementOrderId=''){
        //20220622,增加原始账单获取
        $statementOrderInfo = FinanceStatementOrderService::getInstance($statementOrderId)->get();
        if(!$statementOrderInfo || $statementOrderInfo['need_pay_prize'] != $money){
            //步骤①创建一笔收款明细
            $stOrData['customer_id'] = Arrays::value($statementOrderInfo, 'customer_id');
            $stOrData['user_id'] = Arrays::value($statementOrderInfo, 'user_id');
            $statementOrderInfo = FinanceStatementOrderService::prizeKeySaveRam($prizeKey, $orderId, $money,$stOrData);
        }
        //步骤②生成账单
        $data['statementOrderIds']  = [$statementOrderInfo['id']];
        $data['has_confirm']        = 1;
        $statementInfo              = FinanceStatementService::saveRam( $data );
        //步骤③收款入账
        $accountData['account_id']      =  $accountId;
        $accountData['statement_id']    =  $statementInfo['id'];
        $accountData['bill_time']       =  Arrays::value($stOrData,'bill_time',date('Y-m-d H:i:s'));
        $res = FinanceAccountLogService::saveRam($accountData);
        //更新订单的费用信息（多退少补）；
        /*
        $orderInfo  = OrderService::getInstance( $orderId )->get();
        FinanceStatementOrderService::updateOrderMoneyRam($orderId, $orderInfo['order_prize']);
         */
        //OrderService::getInstance($orderId)->updateFinanceStatementRam();
        //throw new \Exception('测试');
        return $res;
    }
    /**
     * 20220609 后台创建订单，对外付款
     */
    public static function financeOutcomeAdm($orderId,$accountId,$money,$stOrData = [], $prizeKey = 'sellerGoodsPrize'){
        $statementOrderInfo = FinanceStatementOrderService::prizeKeySave($prizeKey, $orderId, $money,$stOrData);
        //步骤②生成账单
        $data['statementOrderIds']  = [$statementOrderInfo['id']];
        $data['has_confirm']        = 1;
        $statementInfo              = FinanceStatementService::save( $data );
        //步骤③收款入账
        $accountData['account_id']      =  $accountId;
        $accountData['statement_id']    =  $statementInfo['id'];
        $res = FinanceAccountLogService::save($accountData);
        return $res;
    }
}

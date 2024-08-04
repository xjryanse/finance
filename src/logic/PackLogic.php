<?php
namespace xjryanse\finance\logic;

use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\logic\Debug;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use Exception;

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
    public static function financeDealRam($orderId,$accountId,$money, $stOrData=[], $prizeKey='' ,$statementOrderId='', $data = [], $accountData=[]){
        // dump(func_get_args());
        //20220622,增加原始账单获取
        // $statementId = FinanceStatementOrderService::mainModel()->newId();
        
        $statementOrderInfo = $statementOrderId ? FinanceStatementOrderService::getInstance($statementOrderId)->get() : [];
        if(!$statementOrderInfo || $statementOrderInfo['need_pay_prize'] != $money){
            //步骤①创建一笔收款明细
            $stOrData['customer_id']    = Arrays::value($statementOrderInfo, 'customer_id');
            $stOrData['user_id']        = Arrays::value($statementOrderInfo, 'user_id');
            $stOrData['order_id']       = $orderId;
            // 20240725
            $stOrData['has_confirm']    = 1;
            $stOrData['has_statement']  = 1;
            // $stOrData['statement_id']   = $statementId;
            // $statementOrderInfo = FinanceStatementOrderService::prizeKeySaveRam($prizeKey, $orderId, $money,$stOrData);
            // 20240121：替换？
            $statementOrderInfo = FinanceStatementOrderService::prizeGetIdForSettle($prizeKey, $money, $stOrData);
        }
        Debug::dump($statementOrderInfo);
        //步骤②生成账单
        $data['statementOrderIds']  = [$statementOrderInfo['id']];
        $data['has_confirm']        = 1;
        $statementInfo              = FinanceStatementService::saveRam( $data );
//        $res = FinanceStatementService::getInstance($statementInfo['id'])->objAttrsList('financeStatementOrder');
//        dump('financeDealRam');
//        dump($res);
        //步骤③收款入账
        $accountData['account_id']      =  $accountId;
        $accountData['statement_id']    =  $statementInfo['id'];
        $accountData['bill_time']       =  Arrays::value($stOrData,'bill_time',date('Y-m-d H:i:s'));
        $res = FinanceAccountLogService::saveRam($accountData);
        //更新订单的费用信息（多退少补）；
        return $res;
    }
    /**
     * 20220731：批量
     * 
     * @param type $orderArr
     * ['order_id'=>$orderId,'sub_id'=>'','need_pay_prize'=>$prize']
     * @param type $orderId
     * @param type $accountId
     * @param type $money
     * @param type $stOrData
     * @param type $prizeKey
     * @param type $statementOrderId
     * @return type
     */
    public static function financeDealBatchRam($orderArr, $accountId, $stOrData=[], $prizeKey='', $data=[]){
        //【1】校验同一客户账单
        $orderIds       = array_column($orderArr,'order_id');
        $cond[]         = ['id','in',$orderIds];
        // $orderLists     = OrderService::mainModel()->where($cond)->select();
        $orderLists     = OrderService::lists($cond);
        $orderListsArr  = $orderLists ? $orderLists->toArray() : [];
        $orderListsObj  = Arrays2d::fieldSetKey($orderListsArr, 'id');
        if(count(array_unique(array_column($orderListsArr,'customer_id'))) > 1){
            // 2023-02-27：付款有bug注释；
            // throw new Exception('请选择同一客户订单');
        }
        //20221024：属性批量提取一次，降低重复查询性能开销。
        OrderService::objAttrsListBatch('financeStatementOrder', $orderIds);        
        OrderService::objAttrsListBatch('orderBuses', $orderIds);        
        // 【2】生成账单
        $statementOrderInfoArr = [];
        foreach($orderArr as &$v){
            $dataSt                     = $stOrData;
            $dataSt['sub_id']           = Arrays::value($v, 'sub_id');
            $dataSt['customer_id']      = Arrays::value($v, 'customer_id')?: Arrays::value($orderListsObj[$v['order_id']], 'customer_id');
            $dataSt['user_id']          = Arrays::value($v, 'user_id')?: Arrays::value($orderListsObj[$v['order_id']], 'user_id');
            //20220803:预写，以免被清
            $dataSt['has_statement']    = 1;
            $dataSt['has_settle']       = 1;
            
            $statementOrderInfo         = FinanceStatementOrderService::prizeKeySaveRam($prizeKey, $v['order_id'], $v['need_pay_prize'],$dataSt);
            $data['statementOrderIds'][] = $statementOrderInfo['id'];
            $statementOrderInfoArr[]    = $statementOrderInfo;
        }

        $data['has_confirm']        = 1;
        $statementInfo              = FinanceStatementService::saveRam( $data );
        //【3】收款入账
        $accountData['account_id']      =  $accountId;
        $accountData['statement_id']    =  $statementInfo['id'];
        $accountData['bill_time']       =  Arrays::value($stOrData,'bill_time',date('Y-m-d H:i:s'));
        $res = FinanceAccountLogService::saveRam($accountData);

        return $res;
    }

    
    /**
     * 20220609 后台创建订单，对外付款
     */
    public static function financeOutcomeAdm($orderId,$accountId,$money,$stOrData = [], $prizeKey = 'sellerGoodsPrize'){
        $statementOrderInfo         = FinanceStatementOrderService::prizeKeySave($prizeKey, $orderId, $money,$stOrData);
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

<?php

namespace xjryanse\finance\service\statement;

use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceManageAccountService;
use Exception;
/**
 * 分页复用列表
 */
trait TriggerTraits {

    public function extraPreDelete() {
        // 2022-11-24:先查
        $hasPay = $this->payQuery();
        if ($hasPay) {
            throw new Exception('系统处理中，请稍后查询' . $this->uuid);
        }

        self::checkTransaction();
        $info = $this->get(0);
        if (Arrays::value($info, 'has_confirm')) {
            throw new Exception('客户已确认对账，不可删');
        }
        //删除对账单的明细
        $con[] = ['statement_id', '=', $this->uuid];
        $statementOrders = FinanceStatementOrderService::lists($con);
        foreach ($statementOrders as $value) {
            //【TODO】一个个删，可能涉及状态更新
//            FinanceStatementOrderService::getInstance($value['id'])->delete();
            //只是把应收的取消了
            FinanceStatementOrderService::getInstance($value['id'])->cancelStatementId();
        }
    }

    public function extraAfterDelete() {
        
    }

    public static function extraPreSave(&$data, $uuid) {
        //【关联已有对账单明细】
        if (isset($data['statementOrderIds'])) {
            //对账订单笔数
            $statementOrderIdCount = count($data['statementOrderIds']);
            $cond[] = ['id', 'in', $data['statementOrderIds']];
            $manageAccountIds = FinanceStatementOrderService::mainModel()->where($cond)->column('distinct manage_account_id');
            if (count($manageAccountIds) > 1) {
                throw new Exception('请选择同一个客户的账单');
            }
            $data['goods_name'] = $data['statementOrderIds'] ? FinanceStatementOrderService::statementOrderGoodsName($data['statementOrderIds']) : '';
            //更新对账单订单的账单id
            foreach ($data['statementOrderIds'] as $value) {
                //财务账单-订单；
                FinanceStatementOrderService::getInstance($value)->setStatementId($uuid);
            }
            //应付金额
            $data['need_pay_prize'] = FinanceStatementOrderService::mainModel()->where($cond)->sum('need_pay_prize');
            //弹一个
            $statementOrderId = array_pop($data['statementOrderIds']);
            $statementOrderInfo = FinanceStatementOrderService::getInstance($statementOrderId)->get(0);
            Debug::debug('FinanceStatementService 的 $statementOrderInfo', $statementOrderInfo);

            $data['customer_id'] = Arrays::value($statementOrderInfo, 'customer_id');
            //[20220518]增加部门
            $data['dept_id'] = Arrays::value($statementOrderInfo, 'dept_id');
            $data['belong_cate'] = Arrays::value($statementOrderInfo, 'belong_cate');
            $data['statement_cate'] = Arrays::value($statementOrderInfo, 'statement_cate');
            $data['user_id'] = Arrays::value($statementOrderInfo, 'user_id');
            $data['manage_account_id'] = Arrays::value($statementOrderInfo, 'manage_account_id');
            $data['statement_name'] = Arrays::value($statementOrderInfo, 'statement_name');
            $data['busier_id'] = Arrays::value($statementOrderInfo, 'busier_id');
            //if($statementOrderIdCount == 1){
            $data['order_id'] = Arrays::value($statementOrderInfo, 'order_id');
            //}
            if ($statementOrderIdCount > 1) {
                $data['statement_name'] .= " 等" . $statementOrderIdCount . "笔";
            }
        }

        Debug::debug('$data', $data);
        //步骤1
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        //生成变动类型
        if (!Arrays::value($data, 'change_type')) {
            $data['change_type'] = $needPayPrize >= 0 ? 1 : 2;
        }
        //步骤2
        $customerId = Arrays::value($data, 'customer_id');
        $userId = Arrays::value($data, 'user_id');
        Debug::debug('$customerId', $customerId);
        Debug::debug('$userId', $userId);
        /* 管理账户id */
        $data['belong_cate'] = $customerId ? 'customer' : 'user';  //账单归属：单位
        $data['manage_account_id'] = FinanceManageAccountService::manageAccountId($customerId, $userId);
        //有订单，拿推荐人
        $orderId = Arrays::value($data, 'order_id');
        if ($orderId) {
            $orderInfo = OrderService::getInstance($orderId)->get(0);
            $data['order_type'] = Arrays::value($orderInfo, 'order_type');
            $data['busier_id'] = Arrays::value($orderInfo, 'busier_id');
        }
        //20210430 客户的应付、供应商的应收，为退款;1应收，2应付
        if ((Arrays::value($data, 'change_type') == '1' && Arrays::value($data, 'statement_cate') == 'seller') || (Arrays::value($data, 'change_type') == '2' && Arrays::value($data, 'statement_cate') == 'buyer')) {
            $data['is_ref'] = 1;
            $data['statement_name'] = '退款 ' . $data['statement_name'];
        } else {
            $data['is_ref'] = 0;
        }
        if ($orderId) {
            $source = OrderService::getInstance($orderId)->fSource();
            //对账单分组
            $data['group'] = $source == 'admin' ? "offline" : "online";
        }
    }

    public static function extraPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        // 20220609:结算状态是否发生改变：用于extraAfterUpdate进行触发更新
        // 有传参，才判断，没传参，认为未发生改变
        $data['settleChange'] = isset($data['has_settle']) ? Arrays::value($data, 'has_settle') != Arrays::value($info, 'has_settle') : false;

    }

    /*
     * 更新商品名称
     */

    public static function extraAfterSave(&$data, $uuid) {
//        $goodsName = FinanceStatementOrderService::statementGoodsName($uuid);
//        return self::mainModel()->where('id',$uuid)->update(['goods_name'=>$goodsName]);
    }

    public static function extraAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__ . __FUNCTION__, $data);
        //20220319，从preUpdate搬迁到AfterUpdate
        self::checkTransaction();
        //20220609，尝试修复bug（结算不删账单，导致脏数据）；if(isset($data['has_settle']) && $hasSettleRaw != $data['has_settle'] ){
        if ($data['settleChange']) {
            if ($data['has_settle']) {
                $accountLogId = Arrays::value($data, 'account_log_id');
                self::getInstance($uuid)->settle($accountLogId);
            } else {
                self::getInstance($uuid)->cancelSettle();
            }
        }

        $upData = [];
        if (isset($data['has_confirm'])) {
            $upData['has_confirm'] = Arrays::value($data, 'has_confirm');
        }
        if (isset($data['has_settle'])) {
            $upData['has_settle'] = Arrays::value($data, 'has_settle');
        }
        if ($upData) {
            $con[] = ['statement_id', '=', $uuid];
            //20220320，启动触发模式
            $statementOrderIds = FinanceStatementOrderService::mainModel()->where($con)->column('id');
            foreach ($statementOrderIds as &$statementOrderId) {
                FinanceStatementOrderService::getInstance($statementOrderId)->update($upData);
            }
        }
    }

    /**
     * 20220620 前序订单保存
     * @param type $data
     * @param type $uuid
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        $statementOrderIds = Arrays::value($data, 'statementOrderIds', []);
        //【关联已有对账单明细】
        if ($statementOrderIds) {
            //对账订单笔数
            $statementOrderIdCount = count($statementOrderIds);
            //提取账单，TODO优化
            $manageAccountIds = [];
            $needPayPrize = 0;
            foreach ($statementOrderIds as $statementOrderId) {
                $info = FinanceStatementOrderService::getInstance($statementOrderId)->get();
                //20220620
                self::getInstance($uuid)->objAttrsPush('financeStatementOrder', $info);
                $manageAccountIds[] = $info['manage_account_id'];
                $needPayPrize += $info['need_pay_prize'];
            }
            if (count(array_unique($manageAccountIds)) > 1) {
                throw new Exception('请选择同一个客户的账单');
            }
            $data['goods_name'] = $statementOrderIds ? FinanceStatementOrderService::statementOrderGoodsName($statementOrderIds) : '';
            //这里还有
            //更新对账单订单的账单id
            foreach ($statementOrderIds as $value) {
                //财务账单-订单；
                FinanceStatementOrderService::getInstance($value)->setStatementIdRam($uuid);
            }
            //这里没有了
            //应付金额
            $data['need_pay_prize'] = $needPayPrize;
            //弹一个
            $statementOrderId = array_pop($statementOrderIds);
            $statementOrderInfo = FinanceStatementOrderService::getInstance($statementOrderId)->get(0);
            Debug::debug('FinanceStatementService 的 $statementOrderInfo', $statementOrderInfo);

            $data['customer_id'] = Arrays::value($statementOrderInfo, 'customer_id');
            //[20220518]增加部门
            $data['dept_id'] = Arrays::value($statementOrderInfo, 'dept_id');
            $data['belong_cate'] = Arrays::value($statementOrderInfo, 'belong_cate');
            $data['statement_cate'] = Arrays::value($statementOrderInfo, 'statement_cate');
            $data['user_id'] = Arrays::value($statementOrderInfo, 'user_id');
            $data['manage_account_id'] = Arrays::value($statementOrderInfo, 'manage_account_id');
            $data['statement_name'] = Arrays::value($statementOrderInfo, 'statement_name');
            $data['busier_id'] = Arrays::value($statementOrderInfo, 'busier_id');
            //if($statementOrderIdCount == 1){
            $data['order_id'] = Arrays::value($statementOrderInfo, 'order_id');
            //}
            if ($statementOrderIdCount > 1) {
                $data['statement_name'] .= " 等" . $statementOrderIdCount . "笔";
            }
        }
        Debug::debug('$data', $data);
        //生成变动类型
        if (!Arrays::value($data, 'change_type')) {
            $data['change_type'] = $needPayPrize >= 0 ? 1 : 2;
        }
        //步骤2
        $customerId = Arrays::value($data, 'customer_id');
        $userId = Arrays::value($data, 'user_id');
        /* 管理账户id */
        if ($customerId) {
            $data['belong_cate'] = 'customer';  //账单归属：单位
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $data['belong_cate'] = 'user';      //账单归属：个人
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $data['manage_account_id'] = $manageAccountId;
        //有订单，拿推荐人
        $orderId = Arrays::value($data, 'order_id');
        if ($orderId) {
            $orderInfo = OrderService::getInstance($orderId)->get(0);
            $data['order_type'] = Arrays::value($orderInfo, 'order_type');
            $data['busier_id'] = Arrays::value($orderInfo, 'busier_id');
        }
        //20210430 客户的应付、供应商的应收，为退款;1应收，2应付
        if ((Arrays::value($data, 'change_type') == '1' && Arrays::value($data, 'statement_cate') == 'seller') || (Arrays::value($data, 'change_type') == '2' && Arrays::value($data, 'statement_cate') == 'buyer')) {
            $data['is_ref'] = 1;
            $data['statement_name'] = '退款 ' . $data['statement_name'];
        } else {
            $data['is_ref'] = 0;
        }
        $dealDirection = Arrays::value($data, 'DIRECTION');
        //前向处理
        if ($orderId && (!$dealDirection || $dealDirection == 'pre')) {
            $source = OrderService::getInstance($orderId)->fSource();
            //对账单分组
            $data['group'] = $source == 'admin' ? "offline" : "online";
        }

        //后向关联保存
        $data['pre_statement_id'] = Arrays::value($data, 'pre_statement_id') ?: self::preUniSave($data);
    }

    public static function ramAfterSave(&$data, $uuid) {
        //后向关联保存
        self::afterUniSave($data);
    }
    
    public static function ramPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        // 20220609:结算状态是否发生改变：用于extraAfterUpdate进行触发更新
        // 有传参，才判断，没传参，认为未发生改变
        $data['settleChange'] = isset($data['has_settle']) ? Arrays::value($data, 'has_settle') != Arrays::value($info, 'has_settle') : false;
        self::getInstance($uuid)->preUniUpdate($data);
    }

    public static function ramAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__ . __FUNCTION__, $data);
        //20220609，尝试修复bug（结算不删账单，导致脏数据）；if(isset($data['has_settle']) && $hasSettleRaw != $data['has_settle'] ){
        if ($data['settleChange']) {
            if ($data['has_settle']) {
                $accountLogId = Arrays::value($data, 'account_log_id');
                self::getInstance($uuid)->settleRam($accountLogId);
            } else {
                self::getInstance($uuid)->cancelSettleRam();
            }
        }

        $upData = [];
        if (isset($data['has_confirm'])) {
            $upData['has_confirm'] = Arrays::value($data, 'has_confirm');
        }
        if (isset($data['has_settle'])) {
            $upData['has_settle'] = Arrays::value($data, 'has_settle');
        }
        if ($upData) {
//            $con[] = ['statement_id','=',$uuid ];
//            //20220320，启动触发模式
//            $statementOrderIds = FinanceStatementOrderService::mainModel()->where($con)->column('id');
            // 2022-11-20
            $statementOrders = self::getInstance($uuid)->objAttrsList('financeStatementOrder');
            $statementOrderIds = array_column($statementOrders, 'id');
            foreach ($statementOrderIds as &$statementOrderId) {
                FinanceStatementOrderService::getInstance($statementOrderId)->updateRam($upData);
            }
        }

        self::getInstance($uuid)->afterUniUpdate($data);
    }

    public function ramPreDelete() {
        self::queryCountCheck(__METHOD__);
        //有前序关联订单，先删前序
        $info = $this->get();
        $preStatementId = $info['pre_statement_id'];
        $tableName = self::mainModel()->getTable();
        if ($preStatementId && !DbOperate::isGlobalDelete($tableName, $preStatementId)) {
            self::getInstance($info['pre_statement_id'])->deleteRam();
        }

        //清除明细的账单
        $statementOrders = $this->objAttrsList('financeStatementOrder');
        foreach ($statementOrders as $value) {
            //只是把应收的取消了
            FinanceStatementOrderService::getInstance($value['id'])->cancelStatementIdRam();
        }
    }

    public function ramAfterDelete($data) {
        //有后序关联订单，再删后序
        $con[] = ['pre_statement_id', '=', $this->uuid];
        $afterIds = self::mainModel()->where($con)->column('id');
        $tableName = self::mainModel()->getTable();
        foreach ($afterIds as $afterId) {
            if ($afterId && !DbOperate::isGlobalDelete($tableName, $afterId)) {
                self::getInstance($afterId)->deleteRam();
            }
        }
    }
    
    
    
    public static function afterUniSave($data) {
        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        if (!$orderId) {
            return false;
        }
        //供应商账单，才进行后向处理
        if ($statementCate != 'seller') {
            return false;
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_AFT) {
            return false;
        }
        //
        $afterOrderArr = OrderService::getInstance($orderId)->getAfterDataArr('pre_order_id');

        foreach ($afterOrderArr as $afterOrderInfo) {
            $statementOrderIds  = [];
            $statementOrders    = OrderService::getInstance($afterOrderInfo['id'])->objAttrsList('financeStatementOrder');
            foreach ($statementOrders as $statementOrder) {
                if (!Arrays::value($statementOrder, 'has_statement')) {
                    $statementOrderIds[] = $statementOrder['id'];
                }
            }
            if ($statementOrderIds) {
                $savData = [];
                $savData['statementOrderIds']   = $statementOrderIds;
                $savData['has_confirm']         = 1;
                //20220620:处理方向：向前
                $savData[DIRECTION]             = DIRECT_AFT;
                $savData['pre_statement_id']    = $data['id'];
                self::saveRam($savData);
            }
        }
        return true;
        /*         * ********************** */
    }

    

    /**
     * 前序关联删除
     */
    public function preUniDelete() {
        
    }

    /**
     * 后续关联删除
     */
    public function afterUniDelete() {
        
    }
    
        /**
     * 20220620
     * @param type $data
     */
    public function afterUniUpdate($data) {
        //无指向，或指向为后向，才进行处理

        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_AFT) {
            return false;
        }
        $afterDataArr = $this->getAfterDataArr('pre_statement_id');
        if (!$afterDataArr) {
            return false;
        }
        //20220622:结算由accountLog触发；取消结算才进行关联处理
        if (isset($data['has_settle']) && !$data['has_settle']) {
            $updData = Arrays::getByKeys($data, ['has_settle', 'has_confirm']);
            $updData[DIRECTION] = DIRECT_AFT;
            foreach ($afterDataArr as $afterData) {
                self::getInstance($afterData['id'])->updateRam($updData);
            }
        }
        return true;
    }

    /**
     * 前序关联保存
     * @param type $thisData        本次保存的数据
     * @param type $preOrderId      前序订单编号
     */
    public static function preUniSave($thisData) {
        $statementOrderIds = Arrays::value($thisData, 'statementOrderIds', []);
        $preStatementOrderIds = [];
        foreach ($statementOrderIds as $statementOrderId) {
            $info = FinanceStatementOrderService::getInstance($statementOrderId)->get();
            if (!$info['pre_statement_order_id']) {
                continue;
            }
            $preStatementOrderInfo = FinanceStatementOrderService::getInstance($info['pre_statement_order_id'])->get();
            if ($preStatementOrderInfo) {
                $preStatementOrderIds[] = $preStatementOrderInfo['id'];
            }
        }

        if (!$preStatementOrderIds) {
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($thisData, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_PRE) {
            return '';
        }

        $data['statementOrderIds'] = $preStatementOrderIds;
        $data['has_confirm'] = 1;
        //20220620:处理方向：向前
        $data['DIRECTION'] = DIRECT_PRE;
        $resData = self::saveRam($data);
        return $resData ? $resData['id'] : "";
    }
    
    /**
     * 20220620
     * @param type $data
     */
    public function preUniUpdate($data) {
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_PRE) {
            return '';
        }
        $preInfo = $this->getPreData('pre_statement_id');
        if (!$preInfo) {
            return false;
        }
        //20220622:结算由accountLog触发；取消结算才进行关联处理
        if (isset($data['has_settle']) && !$data['has_settle']) {
            $updData = Arrays::getByKeys($data, ['has_settle', 'has_confirm']);
            $updData[DIRECTION] = DIRECT_PRE;
            return self::getInstance($preInfo['id'])->updateRam($updData);
        }
    }
}

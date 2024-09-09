<?php

namespace xjryanse\finance\service\statementOrder;

use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceManageAccountService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\finance\logic\PackLogic;
use Exception;

/**
 * 分页复用列表
 */
trait TriggerTraits {

    public function extraPreDelete() {
        Debug::debug(__CLASS__ . __FUNCTION__);
        $this->checkCanDelete();
        self::checkTransaction();
        $info = $this->get();
        if ($info['has_statement'] || $info['statement_id']) {
            throw new Exception('该明细已生成账单，请先删除账单');
        }
        //删除对账单的明细
        if (Arrays::value($info, 'has_settle')) {
            throw new Exception('账单已结不可操作');
        }
        $orderId = Arrays::value($info, 'order_id');
        $statementCate = Arrays::value($info, 'statement_cate');
        //订单表的对账字段
        $orderStatementField = self::getOrderStatementField($statementCate);
        if (OrderService::mainModel()->hasField($orderStatementField)) {
            //订单状态更新为未对账
            OrderService::mainModel()->where('id', $orderId)->update([$orderStatementField => 0]);
        }
        //20220630：删除增加操作；解决订单写入的退款金额bug
        // OrderService::getInstance($orderId)->objAttrsUnSet('financeStatementOrder',$this->uuid);
    }

    /**
     * 删除价格数据
     */
    public function extraAfterDelete($data) {
        // OrderService::getInstance($info['order_id'])->objAttrsUnSet('financeStatementOrder',$this->uuid);
        $orderId = Arrays::value($data, 'order_id');
        if ($orderId) {
            OrderService::getInstance($data['order_id'])->objAttrsUnSet('financeStatementOrder', $this->uuid);
            OrderService::getInstance($data['order_id'])->orderDataSync();
        }
    }

    /**
     * 
     * @param type $data
     * @param type $uuid
     */
    public static function extraPreSave(&$data, $uuid) {
        $keys = ['need_pay_prize', 'statement_cate', 'statement_type'];
        //$notices['order_id']          = '订单id必须';        
        $notices['need_pay_prize'] = '金额必须';
        $notices['statement_cate'] = '对账分类必须';
        $notices['statement_type'] = '费用类型必须';
        DataCheck::must($data, $keys, $notices);
        //账单名称：20210319
        if ($data['order_id']) {
            $data['company_id'] = OrderService::getInstance($data['order_id'])->fCompanyId();
            //20220608:可外部传入
            $data['statement_name'] = Arrays::value($data, 'statement_name') ?: FinanceStatementService::getStatementNameByOrderId($data['order_id'], $data['statement_type']);
        }
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        if (!Arrays::value($data, 'change_type')) {
            $data['change_type'] = $needPayPrize >= 0 ? 1 : 2;
        }
        if (Arrays::value($data, 'change_type')) {
            if (Arrays::value($data, 'change_type') == 1) {
                $data['need_pay_prize'] = abs($needPayPrize); //入账，正值
            }
            if (Arrays::value($data, 'change_type') == 2) {
                $data['need_pay_prize'] = -1 * abs($needPayPrize); //入账，正值
            }
        }
        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        Debug::debug('$statementCate后的$data', $data);
        if ($orderId) {
            //无缓存取数
            $orderInfo = OrderService::getInstance($orderId)->get();
            $data['dept_id'] = Arrays::value($orderInfo, 'dept_id');
            $data['order_type'] = Arrays::value($orderInfo, 'order_type');
            $data['busier_id'] = Arrays::value($orderInfo, 'busier_id');
            //statementCate = "";
            //买家
            if ($statementCate == "buyer") {
                $data['customer_id'] = Arrays::value($orderInfo, 'customer_id');
                $data['user_id'] = Arrays::value($orderInfo, 'user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //卖家
            if ($statementCate == "seller") {
                $data['customer_id'] = isset($data['customer_id']) ? $data['customer_id'] : Arrays::value($orderInfo, 'seller_customer_id');
                $data['user_id'] = isset($data['user_id']) ? $data['user_id'] : Arrays::value($orderInfo, 'seller_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //推荐人
            if ($statementCate == "rec_user" && Arrays::value($orderInfo, 'rec_user_id')) {
                $data['customer_id'] = '';
                $data['user_id'] = Arrays::value($orderInfo, 'rec_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //业务员
            if ($statementCate == "busier" && Arrays::value($orderInfo, 'busier_id')) {
                $data['customer_id'] = '';
                $data['user_id'] = Arrays::value($orderInfo, 'busier_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
        }
        //有否对账单? 1是，0否
        $data['has_statement'] = Arrays::value($data, 'statement_id') ? 1 : 0;
        $data['ref_statement_order_id'] = Arrays::value($data, 'ref_statement_order_id') ? Arrays::value($data, 'ref_statement_order_id') : '';
        //退款订单
        if (Arrays::value($data, 'ref_statement_order_id')) {
            if (self::mainModel()->where('ref_statement_order_id', Arrays::value($data, 'ref_statement_order_id'))->find()) {
                //20220617:似乎是一个扯淡的更新？？
                self::getInstance(Arrays::value($data, 'ref_statement_order_id'))->setHasRef();
            }
        }
        $source = OrderService::getInstance($orderId)->fSource();
        //对账单分组
        $data['group'] = $source == 'admin' ? "offline" : "online";
    }

    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        self::checkTransaction();
        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        //订单表的对账字段
        $orderStatementField = self::getOrderStatementField($statementCate);
        if (OrderService::mainModel()->hasField($orderStatementField)) {
            //订单状态更新为已对账
            OrderService::mainModel()->where('id', $orderId)->update([$orderStatementField => 1]);
        }
        // 写入对象实例
        //$info = self::getInstance($uuid)->get(MASTER_DATA);
//        dump(OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder'));
//        dump($data);
        // 2022-12-22: 发现有bug，无列表属性时，推入会重复。
        // OrderService::getInstance($orderId)->objAttrsPush('financeStatementOrder',$data);
        self::orderAttrFinanceStatementOrderPush($orderId, $data);
        //20220615:处理前序订单的价格：根据需付金额。
        $orderInfo = OrderService::getInstance($orderId)->get();
        if ($orderInfo && $orderInfo['pre_order_id']) {
            $buyerPrize = self::getBuyerPrize($orderId);
            self::updateNeedOutcomePrize($orderInfo['pre_order_id'], $buyerPrize);
        }
        //20220619：收款的，关联前序订单一笔付款；
        //付款的；自动关联后续订单一笔收款；
    }

    public static function extraPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        // 20220609:结算状态是否发生改变：用于extraAfterUpdate进行触发更新
        // 有传参，才判断，没传参，认为未发生改变
        $data['settleChange'] = isset($data['has_settle']) ? Arrays::value($data, 'has_settle') != Arrays::value($info, 'has_settle') : false;
    }

    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__ . __FUNCTION__, $data);
        self::checkTransaction();
        $info = self::getInstance($uuid)->get(0);
        $orderId = Arrays::value($info, 'order_id');
        //订单的金额更新0923：归集集中更新
        //self::orderMoneyUpdate($orderId);
        //是退款单的，把退款金额结算一下
        $refStatementOrderId = Arrays::value($info, 'ref_statement_order_id');

        if ($refStatementOrderId && self::mainModel()->where('ref_statement_order_id', $refStatementOrderId)->find()) {
            $con[] = ['ref_statement_order_id', '=', $refStatementOrderId];
            $con[] = ['has_settle', '=', 1];
            $money = self::sum($con, 'need_pay_prize');
            self::mainModel()->where('id', $refStatementOrderId)->update(['ref_prize' => $money]);
            //更新退款字段的金额
            //self::getInstance( $refStatementOrderId )->update(['ref_prize'=>$money]);   //订单的退款金额
        }
        // 20230804:增加判断，开始处理没有订单id的事宜
        if ($orderId) {
            //[20220518]节点信息更新
            OrderService::getInstance($orderId)->objAttrsUpdate('financeStatementOrder', $uuid, $data);
            //20220320，适用于部分收款后，更新订单的相应金额（流程节点不往前走）
            //20220515取消has_settle == 1判断（考虑人工录入错误的情况）。
            OrderService::getInstance($orderId)->orderDataSync();
        }

        //结算状态有变，才进行处理，20220617：出账怎么办？？？
        if ($data['settleChange'] && Arrays::value($data, 'has_settle') == 1) {
            //20220615:如果有前序订单，自动针对前序订单进行结算
            $orderInfo = OrderService::getInstance($orderId)->get();
            if ($orderInfo && $orderInfo['pre_order_id'] && $info['statement_cate'] = 'buyer') {
                $accountId = FinanceStatementService::getInstance($info['statement_id'])->getAccountId();
                // 财务入账
                PackLogic::financeIncomeAdm($orderInfo['pre_order_id'], $accountId, $info['need_pay_prize']);
            }
        }

        //20230807:临时兼容
        // 20230806:策略模式:处理回调
        self::dealFinanceCallBack($info);
        // 20230903:检测到性能问题
        // DbOperate::dealGlobal();
    }

    /**
     * 删除前
     */
    public function ramPreDelete() {
        self::queryCountCheck(__METHOD__);
        $this->checkCanDelete();
        //有前序关联订单，先删前序
        $info = $this->get();
        if ($info['has_settle']) {
            throw new Exception('已结账单明细不可删' . $this->uuid);
        }

        $preStatementId = $info['pre_statement_order_id'];
        $tableName = self::mainModel()->getTable();
        if ($preStatementId && !DbOperate::isGlobalDelete($tableName, $preStatementId)) {
            self::getInstance($info['pre_statement_order_id'])->deleteRam();
        }
    }

    public function ramAfterDelete($data) {
        //有后序关联订单，再删后序
        $con[] = ['pre_statement_order_id', '=', $this->uuid];
        $afterIds = self::mainModel()->where($con)->column('id');
        $tableName = self::mainModel()->getTable();

        foreach ($afterIds as $afterId) {
            if ($afterId && !DbOperate::isGlobalDelete($tableName, $afterId)) {
                self::getInstance($afterId)->deleteRam();
            }
        }
        //20220621处理订单数据
        // $info = $this->get();
        if ($data['order_id']) {
            OrderService::getInstance($data['order_id'])->objAttrsUnSet('financeStatementOrder', $this->uuid);
            OrderService::getInstance($data['order_id'])->orderDataSyncRam();
        }
    }

    /**
     * 20220620:准备替代extraPreSave方法
     * @param type $data
     * @param type $uuid
     */
    public static function ramPreSave(&$data, $uuid) {
        $data['statement_cate'] = GoodsPrizeKeyService::keyBelongRole($data['statement_type']);
        $keys = ['statement_cate', 'statement_type'];
        $notices['statement_cate'] = '对账分类必须';
        $notices['statement_type'] = '费用类型必须';
        DataCheck::must($data, $keys, $notices);

        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        //账单名称：20210319
        if ($orderId) {
            $data['company_id'] = OrderService::getInstance($orderId)->fCompanyId();
        }
        //20220608:可外部传入
        $data['statement_name'] = Arrays::value($data, 'statement_name') ?
                : FinanceStatementService::getStatementNameByOrderId($orderId, $data['statement_type']);
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        if (!Arrays::value($data, 'change_type')) {
            $data['change_type'] = $needPayPrize >= 0 ? 1 : 2;
        }
        if (Arrays::value($data, 'change_type')) {
            if (Arrays::value($data, 'change_type') == 1) {
                $data['need_pay_prize'] = abs($needPayPrize); //入账，正值
            }
            if (Arrays::value($data, 'change_type') == 2) {
                $data['need_pay_prize'] = -1 * abs($needPayPrize); //入账，正值
            }
        }
        // Debug::debug('$statementCate后的$data', $data);
        if ($orderId) {
            //无缓存取数
            $orderInfo = OrderService::getInstance($orderId)->get();
            $data['dept_id'] = Arrays::value($orderInfo, 'dept_id');
            $data['order_type'] = Arrays::value($orderInfo, 'order_type');
            $data['busier_id'] = Arrays::value($orderInfo, 'busier_id');
            //买家
            if ($statementCate == "buyer") {
                $data['customer_id'] = Arrays::value($orderInfo, 'customer_id');
                $data['user_id'] = Arrays::value($orderInfo, 'user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //卖家
            if ($statementCate == "seller") {
                $data['customer_id'] = isset($data['customer_id']) 
                        ? $data['customer_id'] 
                        : Arrays::value($orderInfo, 'seller_customer_id');
                $data['user_id'] = isset($data['user_id']) 
                        ? $data['user_id'] 
                        : Arrays::value($orderInfo, 'seller_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //推荐人
            if ($statementCate == "rec_user" && Arrays::value($orderInfo, 'rec_user_id')) {
                $data['customer_id'] = '';
                $data['user_id'] = Arrays::value($orderInfo, 'rec_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //业务员
            if ($statementCate == "busier" && Arrays::value($orderInfo, 'busier_id')) {
                $data['customer_id'] = '';
                $data['user_id'] = Arrays::value($orderInfo, 'busier_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
        }
        //有否对账单? 1是，0否
        $data['has_statement']  = Arrays::value($data, 'statement_id') ? 1 : 0;
        $data['has_settle']     = Arrays::value($data, 'has_settle') ? 1 : 0;
        // 20240903:
        $data['has_confirm']    = Arrays::value($data, 'has_confirm') ? 1 : 0;
        $data['ref_statement_order_id'] = Arrays::value($data, 'ref_statement_order_id') 
                ? Arrays::value($data, 'ref_statement_order_id') : '';

        $source = OrderService::getInstance($orderId)->fSource();
        //对账单分组
        $data['group'] = $source == 'admin' ? "offline" : "online";
        //后向关联保存
        //20220622:已有前序账单号，则不需要前序校验判断
        $data['pre_statement_order_id'] = Arrays::value($data, 'pre_statement_order_id') 
                ?: self::preUniSave($data);
        //20220622 ???
        if (!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')) {
            throw new Exception('customer_id和user_id需至少有一个有值');
        }
        // 20231030:增加冗余字段
        self::redunFields($data);
    }

    public static function ramAfterSave(&$data, $uuid) {
        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        //订单表的对账字段
        $orderStatementField = self::getOrderStatementField($statementCate);
        if (OrderService::mainModel()->hasField($orderStatementField)) {
            //订单状态更新为已对账
            OrderService::getInstance($orderId)->setUuData([$orderStatementField => 1]);
        }
        // 2022-12-22: 发现有bug，无列表属性时，推入会重复。
        // OrderService::getInstance($orderId)->objAttrsPush('financeStatementOrder',$data);
        if ($orderId) {
            self::orderAttrFinanceStatementOrderPush($orderId, $data);
        }
        //后向关联保存
        self::afterUniSave($data);
        // 20220620:订单id
        if ($orderId) {
            OrderService::getInstance($orderId)->orderDataSyncRam();
        }
        // 20230807:策略模式回传回调
        $info = self::getInstance($uuid)->get(0);
        self::dealFinanceCallBack($info);
    }
    
    public static function ramPreUpdate(&$data, $uuid) {
        // 20231030:增加冗余字段
        self::redunFields($data);
        // 20240419：更新
        if(Arrays::value($data, 'statement_type')){
            $prizeKey               = Arrays::value($data, 'statement_type');
            $data['statement_cate'] = GoodsPrizeKeyService::keyBelongRole($prizeKey);
        }
    }

    /**
     * 更新后
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterUpdate(&$data, $uuid) {
        $info           = self::getInstance($uuid)->get(0);
        $statementId    = Arrays::value($info, 'statement_id');
        $statementInfo  = FinanceStatementService::getInstance($statementId)->get();
        if(Arrays::value($statementInfo, 'has_confirm') && self::updateDiffsHasField(['need_pay_prize'])){
            throw new Exception('已交款不可修改:'.$uuid);
        }
        
        $orderId = Arrays::value($info, 'order_id');
        OrderService::getInstance($orderId)->objAttrsUpdate('financeStatementOrder', $uuid, $data);
        if ($statementId) {
            FinanceStatementService::getInstance($statementId)->objAttrsUpdate('financeStatementOrder', $uuid, $data);
            // 20230807:改价更新价格
            FinanceStatementService::getInstance($statementId)->updatePrizeRam();
        }

        if ($orderId) {
            OrderService::getInstance($orderId)->updateRam(['status' => 1]);
        }
        // 20230806:策略模式:处理回调
        self::dealFinanceCallBack($info);
        // 20240122:同步源表
        self::getInstance($uuid)->updateBelongTableFinance();
    }

    /**
     * 冗余字段
     */
    public static function redunFields(&$data){
        if(isset($data['customer_id']) && isset($data['user_id'])){
            $customerId     = Arrays::value($data, 'customer_id');
            $userId         = Arrays::value($data, 'user_id');
            $data['manage_account_id'] = FinanceManageAccountService::manageAccountId($customerId, $userId);
        }
        // 20240119：增加!Arrays::value($data, 'belong_table')
        if(Arrays::value($data, 'order_id') && !Arrays::value($data, 'belong_table')){
            $data['belong_table']       = OrderService::getTable();
            $data['belong_table_id']    = $data['order_id'];
        }
    }
    
    
    /**
     * 验证账单是否能删
     */
    protected function checkCanDelete(){
        $info       = $this->get();
        $isNeedPay  = Arrays::value($info, 'is_needpay');
        if($isNeedPay){
            // 20231101；销账bug
            throw new Exception('账单明细应付，不可删除');
        }
    }
    /**
     * 20240122
     * @return bool
     */
    public function updateBelongTableFinance(){
        $info           = $this->get();
        $belongTable    = Arrays::value($info, 'belong_table');
        $belongTableId  = Arrays::value($info, 'belong_table_id');
        if(!$belongTable || !$belongTableId){
            return true;
        }
        $service    = DbOperate::getService($belongTable);
        
        if(method_exists($service, 'updateFinanceRam')){
            // dump('看我这里');
            // 财务端回调
            $service::getInstance($belongTableId)->updateFinanceRam();
        }
    }

}

<?php

namespace xjryanse\finance\service\accountLog;


use xjryanse\logic\Arrays;
use xjryanse\logic\DbOperate;
use xjryanse\logic\DataCheck;
use xjryanse\customer\service\CustomerService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceAccountService;
use xjryanse\finance\service\FinanceManageAccountService;
use xjryanse\finance\service\FinanceManageAccountLogService;
use xjryanse\logic\Debug;
use think\Db;
use Exception;
/**
 * 分页复用列表
 */
trait TriggerTraits{
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        $statementId = Arrays::value($data, 'statement_id'); //对账单id
        if ($statementId) {
            if (self::statementHasLog($statementId)) {
                throw new Exception("该对账单已收款过了，请直接冲账");
            }
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();
            $data['dept_id'] = Arrays::value($statementInfo, 'dept_id');  //不一定有
            $data['customer_id'] = Arrays::value($statementInfo, 'customer_id');  //不一定有
            $data['user_id'] = Arrays::value($statementInfo, 'user_id');      //不一定有            
            $data['busier_id'] = Arrays::value($statementInfo, 'busier_id');      //不一定有      
            $data['change_type'] = Arrays::value($statementInfo, 'change_type');      //不一定有     
            $needPayPrize = Arrays::value($statementInfo, 'need_pay_prize');
            if (!Arrays::value($data, 'money')) {
                $data['money'] = $needPayPrize;
            } else if ($data['money'] != $needPayPrize) {
                throw new Exception('入参金额' . $data['money'] . '和账单金额' . $needPayPrize . '不符,账单号' . $statementId);
            }
        }
        if (!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')) {
            throw new Exception('付款客户或付款用户必须(customer_id/user_id)');
        }
        // Debug::debug('保存信息', $data);
        $notice['account_id'] = "请选择账户";
        $notice['money'] = "金额必须";
        DataCheck::must($data, ['money', 'account_id', 'change_type'], $notice);
        //20220608;增加判断
        if (!Arrays::value($data, 'user_id') && !Arrays::value($data, 'customer_id')) {
            throw new Exception('customer_id和user_id需至少有一个参数有值');
        }
        $fromTable = Arrays::value($data, 'from_table');
        $fromTableId = Arrays::value($data, 'from_table_id');
        if ($fromTable) {
            $service = DbOperate::getService($fromTable);
            $info = $service::getInstance($fromTableId)->get(0);
            if ($service::mainModel()->hasField('into_account')) {
                if ($info['into_account'] != 0) {
                    throw new Exception('非待入账数据不可入账:' . $fromTable . '-' . $fromTableId);
                }
            }
        }
        return $data;
    }
    
    /**
     * 额外输入信息
     * 准备弃用
     */
    public static function extraAfterSave(&$data, $uuid) {
        $customerId     = Arrays::value($data, 'customer_id');  //不一定有
        $accountId      = Arrays::value($data, 'account_id');
        $userId         = Arrays::value($data, 'user_id');      //支付用户（个人）        
        $fromTable      = Arrays::value($data, 'from_table');
        $fromTableId    = Arrays::value($data, 'from_table_id');
        $statementId    = Arrays::value($data, 'statement_id'); //对账单id
        if ($statementId && FinanceStatementService::getInstance($statementId)->fHasSettle()) {
            throw new Exception('账单' . $statementId . '已结算');
        }
        if ($fromTable) {
            $service = DbOperate::getService($fromTable);
            if ($service::mainModel()->hasField('into_account')) {
                $service::getInstance($fromTableId)->update(['into_account' => 1]);    //来源表入账状态更新为已入账
            }
        }
        //更新账户余额
        FinanceAccountService::getInstance($accountId)->updateRemainMoney();
        //更新客户挂账 ???可否取消？？20220617
        if (FinanceAccountService::getInstance($accountId)->fAccountType() == 'customer') {
            $customerMoney = self::customerMoneyCalc($customerId, $accountId);
            CustomerService::mainModel()->where('id', $customerId)->update(['pre_pay_money' => $customerMoney]);
        }
        //最新：更新客户的挂账款流水金额
        if ($customerId) {
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $data2 = Arrays::getByKeys($data, ['money', 'user_id', 'account_id', 'change_type', 'reason']);
        $data2['manage_account_id'] = $manageAccountId;
        $data2['from_table'] = self::mainModel()->getTable();
        $data2['from_table_id'] = $uuid;
        FinanceManageAccountLogService::save($data2);

        //【对账单id】（如有关联对账单id，进行对冲结算）
        if ($statementId) {
            FinanceStatementService::getInstance($statementId)->update(['has_settle' => 1, "account_log_id" => $uuid]);
            //触发关联订单动作
            Debug::debug('FinanceAccountLogService触发关联订单动作', $statementId);
            FinanceStatementOrderService::statementIdTriggerOrderFlow($statementId);
        }
        // 20230531：埋钩子
        self::doTrigger(TRIGGER_AFTER_ORDER_PAY);
    }

    /**
     * 20220620 
     * @param type $data
     * @param type $uuid
     * @return type
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        $statementId = Arrays::value($data, 'statement_id'); //对账单id
        if ($statementId) {
            if (self::statementHasLog($statementId)) {
                throw new Exception("该对账单已收款过了，请直接冲账");
            }
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();
            $data['dept_id']        = Arrays::value($statementInfo, 'dept_id');  //不一定有
            $data['customer_id']    = Arrays::value($statementInfo, 'customer_id');  //不一定有
            $data['user_id']        = Arrays::value($statementInfo, 'user_id');      //不一定有            
            $data['busier_id']      = Arrays::value($statementInfo, 'busier_id');      //不一定有      
            $data['change_type']    = Arrays::value($statementInfo, 'change_type');      //不一定有     
            $needPayPrize = Arrays::value($statementInfo, 'need_pay_prize');
            if (!Arrays::value($data, 'money')) {
                $data['money'] = $needPayPrize;
            } else if ($data['money'] != $needPayPrize) {
                throw new Exception('入参金额' . $data['money'] . '和账单金额' . $needPayPrize . '不符,账单号' . $statementId);
            }
        }
        if (!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')) {
            throw new Exception('付款客户或付款用户必须(customer_id/user_id)');
        }

        Debug::debug('保存信息', $data);
        $notice['account_id'] = "请选择账户";
        $notice['money'] = "金额必须";
        DataCheck::must($data, ['money', 'account_id', 'change_type'], $notice);
        //20220608;增加判断
        if (!Arrays::value($data, 'user_id') && !Arrays::value($data, 'customer_id')) {
            throw new Exception('customer_id和user_id需至少有一个参数有值');
        }
        // 20240420
        $money = Arrays::value($data, 'money');
        if(!intval($money * 100) && !FinanceAccountService::getInstance($data['account_id'])->fCanZero()){
            $accountInfo = FinanceAccountService::getInstance($data['account_id'])->get();
            throw new Exception($accountInfo['account_name'].'不可0金额入账');
        }

        $fromTable = Arrays::value($data, 'from_table');
        $fromTableId = Arrays::value($data, 'from_table_id');
        if ($fromTable) {
            $service = DbOperate::getService($fromTable);
            $info = $service::getInstance($fromTableId)->get(0);
            if ($service::mainModel()->hasField('into_account')) {
                if ($info['into_account'] != 0) {
                    throw new Exception('非待入账数据不可入账:' . $fromTable . '-' . $fromTableId);
                }
            }
        }

        $data['pre_log_id'] = Arrays::value($data, 'pre_log_id') ?: self::preUniSave($data);
        // 20231116
        $data['bill_time'] = Arrays::value($data, 'bill_time') ?: date('Y-m-d H:i:s');
        
        $statementIds = Arrays::value($data, 'statementIds') ? : [];
        if($statementId){
            $statementIds = array_unique(array_merge($statementIds,[$statementId]));
        }
        $data['bill_type'] = FinanceStatementService::calStatementTypeByIds($statementIds);

        self::redunFields($data, $uuid);
        return $data;
    }

    /**
     * 20240302：更新前写入
     * @param type $data
     * @param type $uuid
     */
    public static function ramPreUpdate(&$data, $uuid) {

        self::redunFields($data, $uuid);
        return $data;
    }

    /**
     * 额外输入信息
     */
    public static function ramAfterSave(&$data, $uuid) {
        $customerId = Arrays::value($data, 'customer_id');  //不一定有
        $accountId = Arrays::value($data, 'account_id');
        $userId = Arrays::value($data, 'user_id');      //支付用户（个人）        
        $fromTable = Arrays::value($data, 'from_table');
        $fromTableId = Arrays::value($data, 'from_table_id');
        $statementId = Arrays::value($data, 'statement_id'); //对账单id
        
        // 20231116
        //对账单,多笔id
        $statementIds = Arrays::value($data, 'statementIds') ? : [];
        if($statementId){
            $statementIds = array_unique(array_merge($statementIds,[$statementId]));
        }
        foreach($statementIds as $stId){
            if ($statementId && FinanceStatementService::getInstance($stId)->fHasSettle()) {
                throw new Exception('账单' . $statementId . '已结算');
            }
        }

        if ($fromTable) {
            $service = DbOperate::getService($fromTable);
            if ($service::mainModel()->hasField('into_account')) {
                $service::getInstance($fromTableId)->updateRam(['into_account' => 1]);    //来源表入账状态更新为已入账
            }
        }
        FinanceAccountService::getInstance($accountId)->updateRemainMoneyRam();

        $manageAccountId = FinanceManageAccountService::manageAccountId($customerId, $userId);
        
        $data2 = Arrays::getByKeys($data, ['money', 'user_id', 'account_id', 'change_type', 'reason']);
        $data2['manage_account_id'] = $manageAccountId;
        $data2['from_table'] = self::mainModel()->getTable();
        $data2['from_table_id'] = $uuid;
        FinanceManageAccountLogService::saveRam($data2);

        //【对账单id】（如有关联对账单id，进行对冲结算）
        self::getInstance($uuid)->statementsSettleRam($statementIds);

        self::afterUniSave($data);
        // 20240117:手续费率
        $charge = Arrays::value($data, 'charge');
        
        FinanceStatementOrderService::chargeDistribute($charge, $statementIds, $data);
    }
    // 20230818:更新账户余额
    public static function ramAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        FinanceAccountService::getInstance($info['account_id'])->updateRemainMoneyRam();
    }

    /**
     * 删除
     */
    public function ramPreDelete() {
        $info = $this->get();
        //来源表有记录，则报错
        if ($info['from_table'] && $info['from_table_id']) {
            $conc = [['from_table','=',$info['from_table']],['from_table_id','=',$info['from_table_id']]];
            $count = self::where($conc)->count();
            // 20240727:如果不止一条，可能是程序写错了，后期核查需要删除
            if ($count == 1 && Db::table($info['from_table'])->where('id', $info['from_table_id'])->find()) {
                throw new Exception('请先删除' . $info['from_table'] . '表,id为' . $info['from_table_id'] . '的记录');
            }
        }
        // 20231116:账单取消结算
        $this->statementsCancelSettleRam();
        
        FinanceAccountService::getInstance($info['account_id'])->updateRemainMoneyRam();
    }

    /**
     * 如果statementId,有前序账单，
     * 
     * 
     * @param type $data
     */
    public static function preUniSave($data) {
        DataCheck::must($data, ['account_id']);
        // 对账单id
        $statementId = Arrays::value($data, 'statement_id');
        if (!$statementId) {
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_PRE) {
            return '';
        }
        $preStatementInfo = FinanceStatementService::getInstance($statementId)->getPreData('pre_statement_id');
        if (!$preStatementInfo) {
            return '';
        }

        $accountData['account_id'] = Arrays::value($data, 'account_id');
        $accountData['statement_id'] = $preStatementInfo['id'];
        $accountData['bill_time'] = Arrays::value($data, 'bill_time', date('Y-m-d H:i:s'));
        $accountData[DIRECTION] = DIRECT_PRE;
        $resData = self::saveRam($accountData);
        return $resData ? $resData['id'] : '';
    }

    /**
     * 20220622
     * @param type $data
     * @return boolean|string
     */
    public static function afterUniSave($data) {
        DataCheck::must($data, ['account_id']);
        // 对账单id
        $statementId = Arrays::value($data, 'statement_id');
        if (!$statementId) {
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_AFT) {
            return '';
        }
        $afterStatementInfos = FinanceStatementService::getInstance($statementId)->getAfterDataArr('pre_statement_id');
        foreach ($afterStatementInfos as $afterStatementInfo) {
            //20220622未结算才处理
            $accountData['account_id'] = Arrays::value($data, 'account_id');
            $accountData['statement_id'] = $afterStatementInfo['id'];
            $accountData['pre_log_id'] = $data['id'];
            $accountData['bill_time'] = Arrays::value($data, 'bill_time', date('Y-m-d H:i:s'));
            $accountData[DIRECTION] = DIRECT_AFT;
            self::saveRam($accountData);
        }
        return true;
    }
    /**
     * 20231116：账单结算(多单)
     */
    public function statementsSettleRam($statementIds){
        $upData                     = [];
        $upData['has_settle']       = 1;
        $upData['account_log_id']   = $this->uuid;

        foreach($statementIds as $statementId){
            FinanceStatementService::getInstance($statementId)->updateRam($upData);
        }
    }
    /**
     * 账单取消结算
     */
    public function statementsCancelSettleRam(){
        $con = [];
        $con[] = ['account_log_id','=',$this->uuid];
        $ids = FinanceStatementService::where($con)->column('id');
        foreach($ids as $id){
            // 不校验多笔账单
            FinanceStatementService::getInstance($id)->cancelSettleRam(false);
        }
    }
    /**
     * 20240302
     * 冗余字段
     * @param type $data
     * @param type $uuid
     */
    protected static function redunFields(&$data, $uuid){
        // statement_id，取orders
        // 20240420
        if(isset($data['statement_id'])){
            $statementId =  Arrays::value($data, 'statement_id');
            $data['order_type'] = $statementId 
                    ? FinanceStatementService::getInstance($statementId)->calStatementOrderType() 
                    : '';
        }
        return $data;
    }
}

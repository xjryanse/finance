<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\DbOperate;
use xjryanse\logic\DataCheck;
use xjryanse\customer\service\CustomerService;
use xjryanse\logic\Debug;
use think\Db;
use Exception;
/**
 * 账户流水表
 */
class FinanceAccountLogService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceAccountLog';
    //直接执行后续触发动作
    protected static $directAfter = true;    
    
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        $statementId    = Arrays::value($data, 'statement_id'); //对账单id
        if($statementId){
            if(self::statementHasLog($statementId)){
                throw new Exception("该对账单已收款过了，请直接冲账");
            }
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();
            $data['dept_id']        = Arrays::value($statementInfo, 'dept_id');  //不一定有
            $data['customer_id']    = Arrays::value($statementInfo, 'customer_id');  //不一定有
            $data['user_id']        = Arrays::value($statementInfo, 'user_id');      //不一定有            
            $data['busier_id']      = Arrays::value($statementInfo, 'busier_id');      //不一定有      
            $data['change_type']    = Arrays::value($statementInfo, 'change_type');      //不一定有     
            $needPayPrize           = Arrays::value($statementInfo, 'need_pay_prize');
            if(!Arrays::value($data, 'money')){
                $data['money']          = $needPayPrize;
            } else if($data['money'] != $needPayPrize){
                throw new Exception('入参金额'.$data['money'].'和账单金额'.$needPayPrize.'不符,账单号'.$statementId);
            }
        }
        if(!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')){
            throw new Exception('付款客户或付款用户必须(customer_id/user_id)');
        }

        Debug::debug('保存信息',$data);
        $notice['account_id']   = "请选择账户";
        $notice['money']        = "金额必须";
        DataCheck::must($data, ['money','account_id','change_type'], $notice);
        //20220608;增加判断
        if(!Arrays::value($data, 'user_id') && !Arrays::value($data, 'customer_id')){
            throw new Exception('customer_id和user_id需至少有一个参数有值');
        }

        $fromTable      = Arrays::value($data, 'from_table');
        $fromTableId    = Arrays::value($data, 'from_table_id');
        if($fromTable){
            $service = DbOperate::getService( $fromTable );
            $info = $service::getInstance( $fromTableId )->get(0);
            if( $service::mainModel()->hasField('into_account')){
                if( $info['into_account'] != 0){
                    throw new Exception('非待入账数据不可入账:'.$fromTable.'-'.$fromTableId);
                }
            }
            //customer_id
            // 20220608尝试去除数据
            //$data['customer_id']    = Arrays::value($data, 'customer_id') ? :Arrays::value($info, 'customer_id');
            //$data['user_id']        = Arrays::value($data, 'user_id') ? :Arrays::value($info, 'user_id');
        }

        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        $customerId     = Arrays::value($data, 'customer_id');  //不一定有
        $accountId      = Arrays::value($data, 'account_id');        
        $userId         = Arrays::value($data, 'user_id');      //支付用户（个人）        
        $fromTable      = Arrays::value($data, 'from_table');
        $fromTableId    = Arrays::value($data, 'from_table_id');
        $statementId    = Arrays::value($data, 'statement_id'); //对账单id

        if( $statementId && FinanceStatementService::getInstance($statementId)->fHasSettle() ){
            throw new Exception('账单'.$statementId.'已结算');            
        }
        
        if( $fromTable ){
            $service = DbOperate::getService( $fromTable );
            if( $service::mainModel()->hasField('into_account')){
                $service::getInstance( $fromTableId )->update( ['into_account'=>1]);    //来源表入账状态更新为已入账
            }
        }
        //更新账户余额
        FinanceAccountService::getInstance( $accountId )->updateRemainMoney();
        //更新客户挂账 ???可否取消？？20220617
        if(FinanceAccountService::getInstance($accountId)->fAccountType() == 'customer'){
            $customerMoney = self::customerMoneyCalc($customerId, $accountId);
            CustomerService::mainModel()->where('id',$customerId)->update(['pre_pay_money'=>$customerMoney]);
        }
        
        //最新：更新客户的挂账款流水金额
        if($customerId){
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $data2 = Arrays::getByKeys($data, ['money','user_id','account_id','change_type','reason']);
        $data2['manage_account_id'] = $manageAccountId;
        $data2['from_table']    = self::mainModel()->getTable();
        $data2['from_table_id'] = $uuid;
        FinanceManageAccountLogService::save($data2);
        
        //【对账单id】（如有关联对账单id，进行对冲结算）
        if($statementId){
            FinanceStatementService::getInstance($statementId)->update(['has_settle'=>1,"account_log_id"=>$uuid]);
//            //20210429添加，TODO校验影响
//            $con    = [];
//            $con[]  = ['statement_id','=',$statementId];
//            FinanceStatementOrderService::mainModel()->where($con)->update(['has_settle'=>1]);
            //$data['busier_id'] = FinanceStatementService::getInstance( $statementId )->fBusierId();
            //触发关联订单动作
            Debug::debug('FinanceAccountLogService触发关联订单动作',$statementId);
            FinanceStatementOrderService::statementIdTriggerOrderFlow($statementId);
        }
    }
    
    public function delete()
    {
        $info = $this->get();
        if(Arrays::value($info, 'statement_id')){
            $statementId = Arrays::value($info, 'statement_id');
            $financeStatement = FinanceStatementService::getInstance( $statementId )->get(0);
            if( Arrays::value($financeStatement, 'has_settle') ){
                throw new Exception('关联账单已入账不可操作');
            }
        }
        //来源表有记录，则报错
        if($info['from_table'] && $info['from_table_id']){
            if( Db::table($info['from_table'])->where('id',$info['from_table_id'])->find() ){
                throw new Exception('请先删除'.$info['from_table'].'表,id为'.$info['from_table_id'].'的记录');
            }
        }

        $res = $this->commDelete();
        //更新账户余额
        FinanceAccountService::getInstance( $info['account_id'])->updateRemainMoney();
        //删除管理账的明细
        $con[] = ['from_table','=',self::mainModel()->getTable()];
        $con[] = ['from_table_id','=',$info['id']];
        $lists = FinanceManageAccountLogService::lists( $con );
        foreach($lists as &$v){
            //一个个删，可能有关联
            FinanceManageAccountLogService::getInstance( $v['id'])->delete();
        }
        
        return $res;
    }
    /**
     * 20220620 
     * @param type $data
     * @param type $uuid
     * @return type
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        $statementId    = Arrays::value($data, 'statement_id'); //对账单id
        if($statementId){
            if(self::statementHasLog($statementId)){
                throw new Exception("该对账单已收款过了，请直接冲账");
            }
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();
            $data['dept_id']        = Arrays::value($statementInfo, 'dept_id');  //不一定有
            $data['customer_id']    = Arrays::value($statementInfo, 'customer_id');  //不一定有
            $data['user_id']        = Arrays::value($statementInfo, 'user_id');      //不一定有            
            $data['busier_id']      = Arrays::value($statementInfo, 'busier_id');      //不一定有      
            $data['change_type']    = Arrays::value($statementInfo, 'change_type');      //不一定有     
            $needPayPrize           = Arrays::value($statementInfo, 'need_pay_prize');
            if(!Arrays::value($data, 'money')){
                $data['money']          = $needPayPrize;
            } else if($data['money'] != $needPayPrize){
                throw new Exception('入参金额'.$data['money'].'和账单金额'.$needPayPrize.'不符,账单号'.$statementId);
            }
        }
        if(!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')){
            throw new Exception('付款客户或付款用户必须(customer_id/user_id)');
        }

        Debug::debug('保存信息',$data);
        $notice['account_id']   = "请选择账户";
        $notice['money']        = "金额必须";
        DataCheck::must($data, ['money','account_id','change_type'], $notice);
        //20220608;增加判断
        if(!Arrays::value($data, 'user_id') && !Arrays::value($data, 'customer_id')){
            throw new Exception('customer_id和user_id需至少有一个参数有值');
        }

        $fromTable      = Arrays::value($data, 'from_table');
        $fromTableId    = Arrays::value($data, 'from_table_id');
        if($fromTable){
            $service = DbOperate::getService( $fromTable );
            $info = $service::getInstance( $fromTableId )->get(0);
            if( $service::mainModel()->hasField('into_account')){
                if( $info['into_account'] != 0){
                    throw new Exception('非待入账数据不可入账:'.$fromTable.'-'.$fromTableId);
                }
            }
        }

        $data['pre_log_id'] = Arrays::value($data, 'pre_log_id') ? : self::preUniSave($data);

        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function ramAfterSave(&$data, $uuid) {
        $customerId     = Arrays::value($data, 'customer_id');  //不一定有
        $accountId      = Arrays::value($data, 'account_id');        
        $userId         = Arrays::value($data, 'user_id');      //支付用户（个人）        
        $fromTable      = Arrays::value($data, 'from_table');
        $fromTableId    = Arrays::value($data, 'from_table_id');
        $statementId    = Arrays::value($data, 'statement_id'); //对账单id

        if( $statementId && FinanceStatementService::getInstance($statementId)->fHasSettle() ){
            throw new Exception('账单'.$statementId.'已结算');            
        }
        
        if( $fromTable ){
            $service = DbOperate::getService( $fromTable );
            if( $service::mainModel()->hasField('into_account')){
                $service::getInstance( $fromTableId )->updateRam( ['into_account'=>1]);    //来源表入账状态更新为已入账
            }
        }
        FinanceAccountService::getInstance( $accountId )->updateRemainMoneyRam();
        
        //更新账户余额: 20220620TODObug??
        /*
        FinanceAccountService::getInstance( $accountId )->updateRemainMoney();
        //更新客户挂账 ???可否取消？？20220617
        if(FinanceAccountService::getInstance($accountId)->fAccountType() == 'customer'){
            $customerMoney = self::customerMoneyCalc($customerId, $accountId);
            CustomerService::mainModel()->where('id',$customerId)->update(['pre_pay_money'=>$customerMoney]);
        }
         */
        
        //最新：更新客户的挂账款流水金额
        if($customerId){
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $data2 = Arrays::getByKeys($data, ['money','user_id','account_id','change_type','reason']);
        $data2['manage_account_id'] = $manageAccountId;
        $data2['from_table']    = self::mainModel()->getTable();
        $data2['from_table_id'] = $uuid;
        FinanceManageAccountLogService::saveRam($data2);
        
        //【对账单id】（如有关联对账单id，进行对冲结算）
        if($statementId){
            FinanceStatementService::getInstance($statementId)->updateRam(['has_settle'=>1,"account_log_id"=>$uuid]);
        }
        
        self::afterUniSave($data);   
    }
    /**
     * 删除
     */
    public function ramPreDelete() {
        $info = $this->get();
        FinanceAccountService::getInstance( $info['account_id'] )->updateRemainMoneyRam();
    }
    /**
     * 如果statementId,有前序账单，
     * 
     * 
     * @param type $data
     */
    public static function preUniSave($data){
        DataCheck::must($data, ['account_id']);
        // 对账单id
        $statementId    = Arrays::value($data, 'statement_id'); 
        if(!$statementId){
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_PRE){
            return '';
        }
        $preStatementInfo = FinanceStatementService::getInstance($statementId)->getPreData('pre_statement_id');  
        if(!$preStatementInfo){
            return '';
        }
        
        $accountData['account_id']      =  Arrays::value($data, 'account_id');
        $accountData['statement_id']    =  $preStatementInfo['id'];
        $accountData['bill_time']       =  Arrays::value($data, 'bill_time',date('Y-m-d H:i:s'));
        $accountData[DIRECTION]          = DIRECT_PRE;
        $resData = self::saveRam($accountData);
        return $resData ? $resData['id'] : '';
    }
    /**
     * 20220622
     * @param type $data
     * @return boolean|string
     */
    public static function afterUniSave($data){

        DataCheck::must($data, ['account_id']);
        // 对账单id
        $statementId    = Arrays::value($data, 'statement_id'); 
        if(!$statementId){
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_AFT){
            return '';
        }
        
        $afterStatementInfos = FinanceStatementService::getInstance($statementId)->getAfterDataArr('pre_statement_id');  
        foreach($afterStatementInfos as $afterStatementInfo){
            //20220622未结算才处理
            $accountData['account_id']      = Arrays::value($data, 'account_id');
            $accountData['statement_id']    = $afterStatementInfo['id'];
            $accountData['pre_log_id']      = $data['id'];
            $accountData['bill_time']       = Arrays::value($data, 'bill_time',date('Y-m-d H:i:s'));
            $accountData[DIRECTION]         = DIRECT_AFT;
            self::saveRam($accountData);
        }

        return true;
    }
    
    /**
     * 快速的保存方法
     */
    public static function saveFast(){
        //①保存本类明细
        //②更新总表
        //③保存FinanceManageAccountLogService明细；
        //④保存FinanceManageAccountService总表
        //结算账单总表：w_finance_statement
        //结算账单明细：w_finance_statement_order
        
        //触发关联订单动作
        
        
    }
    /**
     * 对账单是否有收款记录
     * @param type $statementId
     * @return type
     */
    public static function statementHasLog( $statementId )
    {
        $logs = FinanceStatementService::getInstance($statementId)->objAttrsList('financeAccountLog');
        return count($logs);
//        $con[] = ['statement_id','=',$statementId];
//        return self::count($con) ? self::find( $con ) : false;
    }
    /**
     * 20220527
     * 账单id取账户类型
     */
    public static function statementIdsGetAccountType($statementId){
        //20220618:空账单不用查
        if(!$statementId || (is_array($statementId) && count($statementId) == 1 && !$statementId[0])){
            return '';
        }
        $statementIds = is_array($statementId) ? $statementId : [$statementId];
        $accountLogIds = [];
        foreach($statementIds as $stId){
            $statementInfo      = FinanceStatementService::getInstance($stId)->get();
            $accountLogIds[]    = $statementInfo['account_log_id'];
        }
        $accountIds = [];
        foreach($accountLogIds as $logId){
            $logInfo      = self::getInstance($logId)->get();
            $accountIds[]    = $logInfo['account_id'];
        }
        
//
//        //statementId,取accountLog表的accountId
//        $con1[] = ['statement_id','in',$statementId];
//        $accountIds = self::mainModel()->where($con1)->column('distinct account_id');
        //accountId,取类型
        $con2[] = ['id','in',$accountIds];
        $accountTypes = FinanceAccountService::mainModel()->where($con2)->column('distinct account_type');
        return $accountTypes ? (count($accountTypes) > 1 ? 'mix': $accountTypes[0] ) :'';
    }
    
    /**
     * 账单已完结金额;
     * 适用于组合支付中查询金额进行处理
     */
    public static function statementFinishMoney( $statementId ){
        $logs = FinanceStatementService::getInstance($statementId)->objAttrsList('financeAccountLog');
        return array_sum(array_column($logs, 'money'));
        
//        $con[] = ['statement_id','=',$statementId];
//        return self::sum($con,'money');
    }
    /**
     * 来源表和来源id查是否有记录：
     * 一般用于判断该笔记录是否已入账，避免重复入账
     * @param type $fromTable   来源表
     * @param type $fromTableId 来源表id
     */
    public static function hasLog( $fromTable, $fromTableId )
    {
        //`from_table` varchar(255) DEFAULT '' COMMENT '来源表',
        //`from_table_id` varchar(32) DEFAULT '' COMMENT '来源表id',
        $con[] = ['from_table','=',$fromTable];
        $con[] = ['from_table_id','=',$fromTableId];
        
        return self::count($con) ? true : false;
    }
    /**
     * 计算客户端的账户余额
     * @param type $customerId  公司id
     * @param type $accountId   账户id
     */
    public static function customerMoneyCalc( $customerId, $accountId )
    {
        $con[] = ['customer_id','=',$customerId];
        $con[] = ['account_id','=',$accountId];
        return self::mainModel()->where($con)->sum( 'money' );
    }
    
    /**
     *
     */
    public function fId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fAppId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fCompanyId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 账户id，finance_account表
     */
    public function fAccountId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 账号
     */
    public function fEAccountNo() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 1进账，2出账
     */
    public function fChangeType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 金额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 异动原因
     */
    public function fReason() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 单据类型
     */
    public function fBillType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 单据号
     */
    public function fBillSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 资金变动时间
     */
    public function fBillTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 来源表
     */
    public function fFromTable() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 来源表id
     */
    public function fFromTableId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款客户id
     */
    public function fCustomerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款人
     */
    public function fUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 排序
     */
    public function fSort() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 状态(0禁用,1启用)
     */
    public function fStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 有使用(0否,1是)
     */
    public function fHasUsed() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未锁，1：已锁）
     */
    public function fIsLock() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未删，1：已删）
     */
    public function fIsDelete() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 备注
     */
    public function fRemark() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建者，user表
     */
    public function fCreater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新者，user表
     */
    public function fUpdater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建时间
     */
    public function fCreateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新时间
     */
    public function fUpdateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

}

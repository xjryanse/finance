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
    
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        Debug::debug('保存信息',$data);
        $notice['account_id']   = "请选择账户";
        $notice['money']        = "金额必须";
        DataCheck::must($data, ['money','account_id'],$notice);
        $customerId     = Arrays::value($data, 'customer_id');  //不一定有
        $userId         = Arrays::value($data, 'user_id');      //不一定有
        $accountId      = Arrays::value($data, 'account_id');
        $fromTable      = Arrays::value($data, 'from_table');
        $fromTableId    = Arrays::value($data, 'from_table_id');
        $statementId    = Arrays::value($data, 'statement_id'); //对账单id
        $statementCustomerId = FinanceStatementService::getInstance( $statementId )->fCustomerId();
        if( $statementId && $customerId !=$statementCustomerId){
            throw new Exception("收付款客户". $customerId ."与关联账单".$statementId."的客户". $statementCustomerId ."不符");
        }
        if($statementId && (!$customerId && !$userId)){
            throw new Exception("缺少客户".$customerId."和用户".$userId. json_encode($data));
        }    
        if($statementId && self::statementHasLog($statementId)){
            throw new Exception("该对账单已收款过了，请直接冲账");
        }
        if($fromTable){
            $service = DbOperate::getService( $fromTable );
            $info = $service::getInstance( $fromTableId )->get(0);
            if( $service::mainModel()->hasField('into_account')){
                if( $info['into_account'] != 0){
                    throw new Exception('非待入账数据不可入账:'.$fromTable.'-'.$fromTableId);
                }
            }
            //customer_id
            $data['customer_id']    = Arrays::value($data, 'customer_id') ? :Arrays::value($info, 'customer_id');
            $data['user_id']        = Arrays::value($data, 'user_id') ? :Arrays::value($info, 'user_id');
        }
        if(!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')){
            throw new Exception('付款客户或付款用户必须(customer_id/user_id)');
        }
        if(!Arrays::value($data, 'change_type')){
            throw new Exception('未指定出账还是入账');
        }

        //出账，负值
        if( Arrays::value($data, 'change_type') == '2' ){
            $data['money']  = -1 * abs($data['money']);
            //小于客户余额，不可出账
            if( $customerId ){
                $customerMoney = self::customerMoneyCalc($customerId, $accountId);
                if( abs($data['money']) > $customerMoney ){
//                    throw new Exception('该客户最多可退￥'.$customerMoney);
                }
            }
        }
        if( $statementId ){
            $data['busier_id'] = FinanceStatementService::getInstance( $statementId )->fBusierId();
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
            $service::getInstance( $fromTableId )->update( ['into_account'=>1]);    //来源表入账状态更新为已入账
        }
        //更新账户余额
        FinanceAccountService::getInstance( $accountId )->updateRemainMoney();
        //更新客户挂账
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
            //20210429添加，TODO校验影响
            $con    = [];
            $con[]  = ['statement_id','=',$statementId];
            FinanceStatementOrderService::mainModel()->where($con)->update(['has_settle'=>1]);
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
     * 对账单是否有收款记录
     * @param type $statementId
     * @return type
     */
    public static function statementHasLog( $statementId )
    {
        $con[] = ['statement_id','=',$statementId];
        return self::count($con) ? self::find( $con ) : false;
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
        
        return self::count($con) ? self::find( $con ) : false;
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

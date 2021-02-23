<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use Exception;
/**
 * 收款单-订单关联
 */
class FinanceStatementService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatement';

    /**
     * 生成新的对账单
     * @param type $customerId
     * @param type $startTime
     * @param type $endTime
     * @param type $orderIdsArr
     * @param type $data
     */
    public function newStatement( $customerId, $startTime,$endTime,$orderIdsArr,$data=[])
    {
        return false;
        self::checkTransaction();
        $data['customer_id']    = $customerId;
        $data['start_time']     = $startTime;
        $data['end_time']       = $endTime;
        $statementId = self::saveGetId($data);
        
        foreach($orderIdsArr as &$value){
            $value['customer_id']   = $customerId;
            $value['statement_id']  = $statementId;
            $value['statement_cate']  = Arrays::value($data, 'statement_cate');
            if(FinanceStatementOrderService::hasStatement( $customerId, $value['order_id'] )){
                throw new Exception('订单'.$value['order_id'] .'已经对账过了');
            }
            //一个一个添，有涉及其他表的状态更新
            FinanceStatementOrderService::save($value);
        }
//        return FinanceStatementOrderService::saveAll($orderIdsArr);
        return $statementId;
    }
    
    public static function save( $data )
    {
        self::checkTransaction();
        if(!Arrays::value($data, 'order_id')){
            throw new Exception('请选择订单');
        }
        $res = self::commSave($data);
        //转为数组存
        if(is_string($data['order_id'])){
            //单笔订单的存法
            $data['order_id'] = [$data];
        }
        foreach($data['order_id'] as &$value){
            $value['customer_id']       = Arrays::value($data, 'customer_id');
            $value['statement_id']      = $res['id'];
            $value['statement_cate']    = Arrays::value($res, 'statement_cate');
//            if(FinanceStatementOrderService::hasStatement( $customerId, $value['order_id'] )){
//                throw new Exception('订单'.$value['order_id'] .'已经对账过了');
//            }
            //一个一个添，有涉及其他表的状态更新
            FinanceStatementOrderService::save($value);
        }        
        return $res;
    }
    
    public function delete()
    {
        self::checkTransaction();
        $info = $this->get(0);
        if( Arrays::value($info, 'has_confirm') ){
            throw new Exception('客户已确认对账，不可删');
        }
        //删除对账单的明细
        $con[] = ['statement_id','=',$this->uuid];
        $statementOrders = FinanceStatementOrderService::lists( $con );
        foreach( $statementOrders as $value){
            //一个个删，可能涉及状态更新
            FinanceStatementOrderService::getInstance($value['id'])->delete();
        }

        return $this->commDelete();
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        $hasSettle = Arrays::value($data, 'has_settle');
        if( $hasSettle ){
            self::getInstance($uuid)->settle();
        } else {
            self::getInstance($uuid)->cancelSettle();
        }
    }

    /**
     * 对冲结算逻辑
     */
    protected function settle()
    {
        self::checkTransaction();
        if(FinanceManageAccountLogService::hasLog(self::mainModel()->getTable(), $this->uuid)){
            return false;
        }
        $info = $this->get();
        $customerId     = Arrays::value($info, 'customer_id');
        $needPayPrize   = Arrays::value($info, 'need_pay_prize');   //正-他欠我，负-我欠他
        //扣减对冲账户余额
        $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        if($needPayPrize > 0){
            $data['change_type'] = 2;   //客户出账，我进账，客户金额越来越少
        } else {
            $data['change_type'] = 1;   //客户进账，我出账，客户金额越来越多
        }
        $data['manage_account_id'] = $manageAccountId;
        $data['money']          = Arrays::value($info, 'need_pay_prize');
        $data['from_table']     = self::mainModel()->getTable();
        $data['from_table_id']  = $this->uuid;      
        //登记冲账
        FinanceManageAccountLogService::save($data);
        $res = self::mainModel()->where('id',$this->uuid)->update(['has_settle'=>1]);   //更新为已结算
        //冗余
        $con[] = ['statement_id','=',$this->uuid];
        $lists = FinanceStatementOrderService::lists( $con );
        foreach( $lists as $v){
            FinanceStatementOrderService::getInstance( $v['id'] )->update(['has_settle'=>1]);   //更新为已结算
        }
        return $res;
    }
    
    /**
     * 取消对冲结算逻辑
     */
    protected function cancelSettle()
    {
        self::checkTransaction();
        $con[]  =   ['from_table','=',self::mainModel()->getTable()];
        $con[]  =   ['from_table_id','=',$this->uuid];
        $lists = FinanceManageAccountLogService::lists($con);
        foreach( $lists as $v){
            //一个个删，可能关联其他的删除
            FinanceManageAccountLogService::getInstance($v['id'])->delete();
        }
        
        $res = self::mainModel()->where('id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        //冗余
        FinanceStatementOrderService::mainModel()->where('statement_id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        return $res;
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
     * 创建时间
     */
    public function fCreateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建者，user表
     */
    public function fCreater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 客户id
     */
    public function fCustomerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 结束时间
     */
    public function fEndTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 客户已确认（0：未确认，1：已确认）
     */
    public function fHasConfirm() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已收款
     */
    public function fHasSettle() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 有使用(0否,1是)
     */
    public function fHasUsed() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未删，1：已删）
     */
    public function fIsDelete() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未锁，1：已锁）
     */
    public function fIsLock() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 应付金额
     */
    public function fNeedPayPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 备注
     */
    public function fRemark() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 排序
     */
    public function fSort() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 开始时间
     */
    public function fStartTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 对账单类型
     */
    public function fStatementCate() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 对账单名称
     */
    public function fStatementName() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 状态(0禁用,1启用)
     */
    public function fStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新时间
     */
    public function fUpdateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新者，user表
     */
    public function fUpdater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

}

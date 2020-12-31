<?php

namespace xjryanse\finance\service;

use Exception;
/**
 * 付款单
 */
class FinanceOutcomeService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceOutcome';

    public function delete()
    {
        //多表删除需事务
        self::checkTransaction();
        //执行主表删除
        $res = $this->commDelete();
        //删除收款关联订单
        $con[] = ['outcome_id','=',$this->uuid];
        $lists = FinanceOutcomeOrderService::lists( $con );
        foreach( $lists as $key=>$value){
            if( $value['outcome_status'] == XJRYANSE_OP_FINISH){
                throw new Exception('付款单对应订单已完成付款，收款单不可删除。记录id：'.$value['id']);
            }
            //删除
            FinanceOutcomeOrderService::getInstance( $value['id'] )->delete();
        }
        
        //删除支付单
        $outcomePays = FinanceOutcomePayService::lists( $con );
        foreach( $outcomePays as $v){
            FinanceOutcomePayService::getInstance( $v['id'] )->delete();
        }

        return $res;
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
     * 付款单号
     */
    public function fOutcomeSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款类型：
     */
    public function fOutcomeType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款状态//1待收款、2已收款、3订单取消
     */
    public function fOutcomeStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款金额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款描述
     */
    public function fDescribe() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 客户id，tr_customer表
     */
    public function fCustomerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付单号
     */
    public function fPaySn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付渠道
     */
    public function fPayFrom() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付用户id
     */
    public function fPayUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付用户姓名
     */
    public function fPayUserName() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款人id
     */
    public function fOutcomeUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款人姓名
     */
    public function fOutcomeUserName() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款时间
     */
    public function fOutcomeTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 入账状态：0未入账，1已入账
     */
    public function fIntoAccount() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 财务入账人id
     */
    public function fIntoAccountUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 财务入账人姓名
     */
    public function fIntoAccountUserName() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 入账时间
     */
    public function fIntoAccountTime() {
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

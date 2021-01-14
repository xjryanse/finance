<?php

namespace xjryanse\finance\service;

use xjryanse\finance\logic\FinanceAccountLogic;
use Exception;

/**
 * 收款记录表：用户支付
 */
class FinanceIncomePayService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceIncomePay';
    
    /**
     * 根据收款单id获取支付来源
     */
    public static function columnPayByByIncomeId( $incomeId )
    {
        $con[] = ['income_id','=',$incomeId];
        $con[] = ['income_status','=',XJRYANSE_OP_FINISH];
        return self::column('distinct pay_by', $con);
    }
    /*
     * 支付单转收款id
     */
    public static function paySnToId($sn) {
        return self::where('income_pay_sn', $sn)->value('income_id');
    }
    /**
     * 支付单转收款单id
     * @param type $sn
     * @return type
     */
    public static function paySnToIncomeId($sn) {
        return self::where('income_pay_sn', $sn)->value('income_id');
    }
    
    /**
     * 获取订单的支付单号
     */
    public static function incomeGetPaySn($incomeId) {
        //TODO,状态判断
        $con[] = ['income_id', '=', $incomeId];
        return self::where($con)->order('id desc')->value('income_pay_sn');
    }

    /**
     * 新的支付记录
     */
    public static function newIncomePay($incomeId, $money, $data = []) {
        $data['income_id'] = $incomeId;
        $data['money'] = $money;

        $res = self::save($data);
        return $res;
    }
    
    /**
     * 取消支付
     */
    public function cancelPay( )
    {
        //支付单关闭
        $financeIncomePay = $this->get(0);
        if( !$financeIncomePay ){
            throw new Exception( '支付单'. $this->uuid.'不存在' );
        }
        if( $financeIncomePay['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception( '支付单'. $this->uuid .'非待收款状态不能操作' );
        }
        //支付单更新为已关闭状态
        $res = $this->setFieldWithPreValCheck('income_status',XJRYANSE_OP_TODO,XJRYANSE_OP_CLOSE );
        return $res;
    }
    
    /**
     * 支付后确认收款【更新状态】
     * @param type $financeIncomePayId  支付单id
     */
    public function afterPayDoIncome( )
    {
        //校验事务
        self::checkTransaction();
        //支付单关闭
        $financeIncomePay = $this->get(0);
        if( !$financeIncomePay ){
            throw new Exception( '支付单'.$this->uuid.'不存在' );
        }
        if( $financeIncomePay['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception( '支付单'.$this->uuid.'非待收款状态不能操作' );
        }
        //支付单更新为已完成
        $res = $this->setFieldWithPreValCheck('income_status',XJRYANSE_OP_TODO,XJRYANSE_OP_FINISH );
        return $res;
    }
    
    /**
     * 支付单，单笔入账
     */
    public function intoAccount()
    {
        //校验事务
        self::checkTransaction();
        //获取支付单信息
        $incomePay                  = $this->get();        
        //拼接入账数据
        $logData                    = [];
        $logData['reason']          = $incomePay['describe'];
        $logData['from_table']      = self::mainModel()->getTable();
        $logData['from_table_id']   = $incomePay['id'];
        $logData['user_id']         = $incomePay['user_id'];            
        //收款单入账
        $res = FinanceAccountLogic::doIncome($incomePay['company_id'], $incomePay['pay_by'], $incomePay['money'] ,$logData);
        //设定入账状态为已入账
        $this->setField( 'into_account',1 );
        return $res;
    }

    public function delete() {
        $info = $this->get(0);
        if (!$info) {
            throw new Exception('记录不存在');
        }
        //特殊判断
        if ($info['income_status'] != XJRYANSE_OP_TODO) {
            throw new Exception('非待收款状态不能操作');
        }

        return self::mainModel()->where('id', $this->uuid)->delete();
    }

    public static function getBySn($sn) {
        $con[] = ['income_pay_sn', '=', $sn];
        return self::find($con);
    }

    /**
     * 根据订单id，取收款单id数组
     */
    public static function columnIncomePaySnByIncomeId($incomeId, $con = []) {
        $con[] = ['income_id', 'in', $incomeId];
        return self::mainModel()->where($con)->column('income_pay_sn');
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
     * 支付单号
     */
    public function fIncomePaySn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付渠道：wechat:微信；money:余额
     */
    public function fPayBy() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单id，tr_finance表
     */
    public function fIncomeId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单-金额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单-号
     */
    public function fIncomeSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款状态//todo待收款、finish已收款、close订单关闭
     */
    public function fIncomeStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付描述
     */
    public function fDescribe() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付客户id
     */
    public function fCustomerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付用户id
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

<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\order\service\OrderService;
/**
 * 收款单-订单关联
 */
class FinanceStatementOrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatementOrder';

    public static function extraPreSave(&$data, $uuid) {
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        if($needPayPrize >= 0){
            $data['change_type'] = 1;
        } else {
            $data['change_type'] = 2;
        }
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        self::checkTransaction();
        $orderId                = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');
        //订单表的对账字段
        $orderStatementField    = self::getOrderStatementField($statementCate);
        if(OrderService::mainModel()->hasField($orderStatementField)){
            //订单状态更新为已对账
            OrderService::mainModel()->where('id',$orderId)->update([$orderStatementField=>1]);
            //订单的金额更新
            self::orderMoneyUpdate($orderId);
        }
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        self::checkTransaction();
        $info       = self::getInstance( $uuid )->get();
        $orderId    = Arrays::value( $info , 'order_id');
        //订单的金额更新
        self::orderMoneyUpdate($orderId);
    }    
    /**
     * 订单表的对账字段
     */
    private static function getOrderStatementField($statementCate)
    {
        return 'has_'. ($statementCate ? $statementCate.'_' : '') .'statement';
    }

    public function delete()
    {
        self::checkTransaction();
        //删除对账单的明细
        $info = $this->get(0);
        $orderId                = Arrays::value($info, 'order_id');
        $statementCate          = Arrays::value($info, 'statement_cate');        
        //订单表的对账字段
        $orderStatementField    = self::getOrderStatementField($statementCate);
        //订单状态更新为未对账
        OrderService::mainModel()->where('id',$orderId)->update([$orderStatementField=>0]);
        //删除对账订单。
        $res = $this->commDelete();
        //订单的金额更新
        self::orderMoneyUpdate($orderId);
        return $res;
    }    
    
    /**
     * 统计订单已付金额
     * @param type $orderId
     * @return type
     */
    protected static function orderSettleMoneyCalc( $orderId ,$con = [])
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',1];
        return self::mainModel()->where($con)->sum( 'need_pay_prize' );
    }
    /**
     * 订单金额更新
     */
    protected static function orderMoneyUpdate( $orderId )
    {
        //已收金额
        $con1[] = ['statement_cate','=','buyer'];
        $con1[] = ['change_type','=','1'];
        $data["pay_prize"]      = self::orderSettleMoneyCalc( $orderId, $con1);
        //已付金额
        $con2[] = ['statement_cate','=','seller'];
        $con2[] = ['change_type','=','2'];
        $data["outcome_prize"]  = self::orderSettleMoneyCalc( $orderId, $con2 );
        //毛利
        $data["final_prize"]    = self::orderSettleMoneyCalc( $orderId );
        //更新金额
        OrderService::getInstance($orderId)->update( $data );
//  `pre_prize` decimal(10,2) DEFAULT '0.00' COMMENT '最小定金，关联发车付款进度',
//  `order_prize` decimal(10,2) DEFAULT '0.00' COMMENT '订单金额，关联发车付款进度',
//  `pay_prize` decimal(10,2) DEFAULT '0.00' COMMENT '已收金额',
//  `refund_prize` decimal(10,2) DEFAULT '0.00' COMMENT '收退金额',
//  `outcome_prize` decimal(10,2) DEFAULT '0.00' COMMENT '已付金额',
//  `outcome_refund_prize` decimal(10,2) DEFAULT '0.00' COMMENT '付退金额',
//  `distri_prize` decimal(10,2) DEFAULT '0.00' COMMENT '已分派金额',
    }
    
    /*
     * 订单是否已对账
     * TODO 优化 一笔订单在一个客户下只对账一次。
     */
    public static function hasStatement( $customerId, $orderId )
    {
        $con[] = ['customer_id','=',$customerId];
        $con[] = ['order_id','=',$orderId];
        return self::mainModel()->where( $con )->value('id');
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
     * 订单id
     */
    public function fOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单金额
     */
    public function fOrderPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已付金额
     */
    public function fPayPrize() {
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
     * 对账单id
     */
    public function fStatementId() {
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

<?php

namespace xjryanse\finance\service;

/**
 * 收款单-订单关联
 */
class FinanceIncomeOrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceIncomeOrder';

    /*
     * 获取订单费用
     * @param type $orderId     订单id
     * @param type $status      收款状态，默认已完成
     */

    public static function getOrderMoney($orderId, $status = XJRYANSE_OP_FINISH) {
        $con[] = ['order_id', '=', $orderId];
        if ($status) {
            $con[] = ['income_status', 'in', $status];
        }
        $res = self::sum($con, 'money');
        //四舍五入
        return round($res, 2);
    }

    /*
     * 获取收款单费用
     * @param type $incomeId    收款单id
     * @param type $status      收款状态，默认已完成
     */

    public static function getIncomeMoney($incomeId, $status = XJRYANSE_OP_FINISH) {
        $con[] = ['income_id', '=', $incomeId];
        if ($status) {
            $con[] = ['income_status', 'in', $status];
        }
        return self::sum($con, 'money');
    }

    /**
     * 根据订单id，取收款单id数组
     */
    public static function columnIncomeIdByOrderId($orderId, $con = []) {
        $con[] = ['order_id', 'in', $orderId];
        return self::mainModel()->where($con)->column('income_id');
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
     * 订单表id
     */
    public function fOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单id
     */
    public function fIncomeId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 价格key
     */
    public function fPrizeKey() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 归属金额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 冗余记录订单号
     */
    public function fEOrderSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单号
     */
    public function fEIncomeSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款类型1拼车付款,2个人包车定金,3个人包车尾款,4月结收款
     */
    public function fEIncomeType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已退款金额
     */
    public function fRefundMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款状态//todo待收款、finish已收款、close订单关闭
     */
    public function fIncomeStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 入账状态：0未入账，1已入账
     */
    public function fEIntoAccount() {
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

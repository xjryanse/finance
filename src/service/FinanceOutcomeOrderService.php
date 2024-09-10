<?php

namespace xjryanse\finance\service;

use xjryanse\order\logic\OrderLogic;

/**
 * 付款单-订单关联
 */
class FinanceOutcomeOrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;


    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceOutcomeOrder';

    /*
     * 获取订单费用
     * @param type $orderId     订单id
     * @param type $status      收款状态，默认已完成
     */

    public static function getOrderMoney($orderId, $status = XJRYANSE_OP_FINISH) {
        $con[] = ['order_id', '=', $orderId];
        if ($status) {
            $con[] = ['outcome_status', 'in', $status];
        }
        $res = self::sum($con, 'money');
        //四舍五入
        return round($res, 2);
    }

    public static function save($data) {
        $res = self::commSave($data);
        if (isset($data['order_id'])) {
            OrderLogic::financeSync($data['order_id']);
        }
        return $res;
    }

    /**
     * 更新
     * @param array $data
     * @return type
     * @throws Exception
     */
    public function update(array $data) {
        $incomeId = isset($data['outcome_id']) ? $data['outcome_id'] : self::getInstance($this->uuid)->fIncomeId();

//        $data['pay_by'] = FinanceOutcomeService::getInstance($incomeId)->fieldValue('pay_by','',0);  //不拿缓存
        if (isset($data['file_id'])) {
            // FinanceIncomeService::getInstance($incomeId)->update(['file_id' => $data['file_id']]);
        }

        $orderId = $this->fOrderId();
        OrderLogic::financeSync($orderId);

        //预保存数据
        return $this->commUpdate($data);
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
     * 付款单id
     */
    public function fOutcomeId() {
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
     * 付款单号
     */
    public function fEOutcomeSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款类型:
     */
    public function fEOutcomeType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已退款金额
     */
    public function fRefundMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款状态//1待收款、2已收款、3订单取消
     */
    public function fEOutcomeStatus() {
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

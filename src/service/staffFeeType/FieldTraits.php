<?php

namespace xjryanse\finance\service\staffFeeType;

/**
 * 
 */
trait FieldTraits{

    public function fFeeKey() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 费用归属：office办公室；driver司机；
     */
    public function fFeeGroup() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 报销单号
     */
    public function fFeeSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 出纳状态：0待审批；1已同意，2已拒绝
     */
    public function fFinStatus() {
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
     * 报销金额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 状态：0待支付；1已支付
     */
    public function fPayStatus() {
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
     * 状态(0禁用,1启用)
     */
    public function fStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 报销类别
     */
    public function fType() {
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

    /**
     * 报销人
     */
    public function fUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
}

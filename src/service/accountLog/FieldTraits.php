<?php

namespace xjryanse\finance\service\accountLog;

/**
 * 分页复用列表
 */
trait FieldTraits{
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
    
    public function fStatementId() {
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

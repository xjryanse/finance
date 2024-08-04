<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use xjryanse\order\service\OrderService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\finance\logic\UserPayLogic;
use xjryanse\wechat\service\WechatWxPayConfigService;
use xjryanse\wechat\service\WechatWxPayLogService;
use think\Db;
use Exception;

/**
 * 收款单-订单关联
 */
class FinanceSubjectService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceSubject';
    //直接执行后续触发动作
    protected static $directAfter = true;

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

    public function fBusierId() {
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

    public function fOrderId() {
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

    public function fStatementType() {
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

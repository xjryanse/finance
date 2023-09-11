<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use Exception;

/**
 * 
 */
class FinanceStaffFeeListService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFeeList';
    //直接执行后续触发动作
    protected static $directAfter = true;

    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    public static function ramPreSave(&$data, $uuid) {

    }
    /**
     * 
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterSave(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if ($info['fee_id']) {
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdateRam();
        }
    }

    public static function ramAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if ($info['fee_id']) {
            $upData = is_object($info) ? $info->toArray() : [];
            FinanceStaffFeeService::getInstance($info['fee_id'])->objAttrsUpdate('financeStaffFeeList', $uuid, $upData);
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdateRam();
        }
    }

    public function ramPreDelete() {
        $info = $this->get();
        $staffFeeInfo = FinanceStaffFeeService::getInstance($info['fee_id'])->get();
        if ($staffFeeInfo['has_settle']) {
            throw new Exception('报销单已支付不可删');
        }

        FinanceStaffFeeService::getInstance($info['fee_id'])->objAttrsUnSet('financeStaffFeeList', $this->uuid);
        FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdateRam();
    }

    /**
     * 钩子-删除后
     */
    public function ramAfterDelete() {
        
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    /**
     * 钩子-更新后
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if ($info['fee_id']) {
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdate();
        }
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete() {
        
    }

    /**
     * 钩子-删除后
     */
    public function extraAfterDelete() {
        
    }

    /**
     * 重写父类
     * @return type
     */
    public function delete() {
        $info = $this->get();
        //删除前
        $this->extraPreDelete();      //注：id在preSaveData方法中生成
        //删除
        $res = $this->commDelete();
        //特殊处理删除方法
        if ($info['fee_id']) {
            FinanceStaffFeeService::getInstance($info['fee_id'])->feeMoneyUpdate();
        }

        //删除后
        $this->extraAfterDelete();      //注：id在preSaveData方法中生成

        return $res;
    }

    /**
     * 会计状态：0待审批；1已同意，2已拒绝
     */
    public function fAccStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 附件
     */
    public function fAnnex() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 申请时间
     */
    public function fApplyTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 业务状态：0待审批；1已同意，2已拒绝
     */
    public function fBossStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 车号
     */
    public function fBusId() {
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

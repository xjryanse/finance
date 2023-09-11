<?php

namespace xjryanse\finance\service;

use xjryanse\finance\service\FinanceIncomePayService;
use Exception;

/**
 * 收款单
 */
class FinanceIncomeService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceIncome';

    public function delete() {
        //多表删除需事务
        self::checkTransaction();
        //执行主表删除
        $res = $this->commDelete();
        //删除收款关联订单
        $con[] = ['income_id', '=', $this->uuid];
        $lists = FinanceIncomeOrderService::lists($con);
        foreach ($lists as $key => $value) {
            if ($value['income_status'] == XJRYANSE_OP_FINISH) {
                throw new Exception('收款单对应订单已完成收款，收款单不可删除。记录id：' . $value['id']);
            }
            //删除
            FinanceIncomeOrderService::getInstance($value['id'])->delete();
        }

        //删除支付单
        $incomePays = FinanceIncomePayService::lists($con);
        foreach ($incomePays as $v) {
            FinanceIncomePayService::getInstance($v['id'])->delete();
        }

        return $res;
    }

    /**
     * 
     * @param type $data
     */
    public static function save($data) {
        $res = self::commSave($data);
        if (isset($data['order_id'])) {
            $data['income_id'] = $res['id'];
            FinanceIncomeOrderService::save($data);
            //收款单
            if (isset($data['income_status']) && $data['income_status'] == XJRYANSE_OP_FINISH) {
                FinanceIncomePayService::saveGetId($data);
            }
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
        $payBy = FinanceIncomePayService::columnPayByByIncomeId($this->uuid);
        $data['pay_by'] = implode(',', $payBy); //支付来源
        //预保存数据
        if ($this->get()) {
            return $this->commUpdate($data);
        }
    }

    public static function getBySn($sn) {
        $con[] = ['income_sn', '=', $sn];
        return self::find($con);
    }

    public static function snToId($sn) {
        return Finance::where('finance_sn', $sn)->value('id');
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

    public function fOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单号
     */
    public function fIncomeSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款类型：
     */
    public function fIncomeType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款状态//todo待收款、finish已收款、close订单关闭
     */
    public function fIncomeStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款金额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款描述
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
     * 支付来源
     */
    public function fPayBy() {
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
     * 收款人id
     */
    public function fIncomeUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款人姓名
     */
    public function fIncomeUserName() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款时间
     */
    public function fIncomeTime() {
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

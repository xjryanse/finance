<?php

namespace xjryanse\finance\service;

use xjryanse\logic\SnowFlake;
use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceIncomePayService;

/**
 * 退款
 */
class FinanceRefundOutcomeService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceRefundOutcome';
    //直接执行后续触发动作
    protected static $directAfter = true;

    /*
     * 获取订单费用
     * @param type $orderId     订单id
     * @param type $status      收款状态，默认已完成
     */

    public static function getOrderMoney($orderId, $status = XJRYANSE_OP_FINISH) {
        $con[] = ['order_id', '=', $orderId];
        if ($status) {
            $con[] = ['refund_status', 'in', $status];
        }
        $res = self::sum($con, 'refund_prize');
        //四舍五入
        return round($res, 2);
    }

    /**
     * 新订单写入
     * @param type $orderId     订单id
     * @param type $refundPrize 退款金额
     * @param type $paySn       支付单号
     * @param type $data        额外数据
     * @return type
     */
    public static function newRefund($orderId, $refundPrize, $paySn, $data = []) {
        //【订单】
        $orderInfo = OrderService::getInstance($orderId)->get();
        //订单类型
        $data['order_type'] = isset($orderInfo['order_type']) ? $orderInfo['order_type'] : '';
        //【支付单】
        $payInfo = FinanceIncomePayService::getBySn($paySn);
        //订单类型
        $data['income_id'] = isset($payInfo['income_id']) ? $payInfo['income_id'] : '';
        $data['order_id'] = $orderId;     //订单id
        $data['refund_prize'] = $refundPrize; //退款金额
        $data['pay_sn'] = $paySn;       //支付单号
        //生成收款单
        $data['id'] = SnowFlake::generateParticle();
        $data['company_id'] = session('scopeCompanyId');
        $data['refund_sn'] = 'REF' . $data['id'];
        $data['refund_status'] = isset($data['refund_status']) ? $data['refund_status'] : XJRYANSE_OP_TODO;

        return self::save($data);
    }

    /**
     * 发起退款
     * @param type $orderId     订单号
     * @param type $financeSn   收款单号
     * @param type $refundMoney 退款金额
     */
    public static function refund($orderId, $financeSn, $refundMoney) {
        self::checkTransaction();
        //获取收款单id
        $incomeId = FinanceIncomeService::snToId($financeSn);
        //订单号和收款单号查询收款单id：
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['income_id', '=', $incomeId];
        $financeOrder = FinanceIncomeOrderService::find($con);
        $finance = FinanceIncomeService::get($financeOrder['income_id']);

        //获取订单的已付金额-已退金额。小于0报错。
        $orderInfo = OrderService::getInstance($orderId)->get();
        if ($orderInfo['pay_prize'] < ($orderInfo['refund_prize'] + $refundMoney)) {
            throw new Exception('退款超出已付金额，总支付：' . $orderInfo['pay_prize'] . '总已退:' . $orderInfo['refund_prize'] . '本次申请退:' . $refundMoney);
        }
        if ($financeOrder ['money'] < ($financeOrder ['refund_money'] + $refundMoney)) {
            throw new Exception('退款超出当单金额');
        }
        //TODO更新到已退金额中
        $financeOrder->refund_money = $financeOrder ['refund_money'] + $refundMoney;
        $financeOrder->save();
        //生成一个退款单号
        return self::newRefund($orderInfo['order_type'], $financeOrder["order_id"], $finance['money'], $refundMoney, $financeSn);
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
     * 订单id
     */
    public function fOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 退款单号：
     */
    public function fRefundSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 退款渠道
     */
    public function fRefundFrom() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 退款原因
     */
    public function fRefundReason() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单类型
     */
    public function fOrderType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单号：
     */
    public function fOrderSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 收款单id：
     */
    public function fIncomeId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 支付单号
     */
    public function fPaySn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单金额
     */
    public function fOrderPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 退款金额
     */
    public function fRefundPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 退款状态//todo待处理、doing进行中、finish已退款、close已关闭
     */
    public function fRefundStatus() {
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

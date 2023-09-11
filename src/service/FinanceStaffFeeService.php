<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\approval\interfaces\ApprovalOutInterface;
use xjryanse\approval\service\ApprovalThingService;
use xjryanse\goods\service\GoodsService;
use xjryanse\order\service\OrderService;
use xjryanse\user\service\UserService;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\DataCheck;
use app\bus\service\BusService;
use Exception;

/**
 * 
 */
class FinanceStaffFeeService implements MainModelInterface, ApprovalOutInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\ObjectAttrTrait;
    use \xjryanse\approval\traits\ApprovalOutTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFee';
    //直接执行后续触发动作
    protected static $directAfter = true;

    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid){
        self::stopUse(__METHOD__);
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete(){
        self::stopUse(__METHOD__);
    }

//    /**
//     * 20220623生成订单
//     */
//    public function generateOrderGetId() {
//        $info = $this->get();
//        if (Arrays::value($info, 'order_id')) {
//            return $info['order_id'];
//        }
//        //部门
//        $data['dept_id'] = $info['dept_id'];
//        //报销人
//        $data['seller_user_id'] = $info['user_id'];
//        //20220623 TODO 兼容报错
//        $data['user_id'] = $info['user_id'];
//        //报销时间
//        $data['plan_start_time'] = $info['apply_time'];
//
//        $saleType = ORDER_TYPE_STAFF_FEE;
//        $goodsInfo = GoodsService::getBySaleType($saleType);
//        $data['order_type'] = $saleType;    //订单类型默认包车
//        $goodsArr[] = ['goods_id' => $goodsInfo['id'], 'amount' => 1];
//        $orderId = OrderService::orderRam($goodsArr, $data);
//        $this->updateRam(['order_id' => $orderId]);
//
//        return $orderId;
//    }

    /**
     * 20220623，取消订单id
     */
    public function cancelOrder() {
        self::stopUse(__METHOD__);
        // $this->updateRam(['order_id' => '']);
    }

    public static function ramPreSave(&$data, $uuid) {
        $keys = ['user_id','feeArr'];
        $notice['user_id']  = '报销人必须';
        $notice['feeArr']   = '报销项目必填';
        DataCheck::must($data, $keys, $notice);
        // 20230730校验报销项目金额是否大于0 
        if(array_sum(array_values($data['feeArr'])) == 0){
            throw new Exception('报销金额不能为0');
        }
        
        $userId     = Arrays::value($data, 'user_id');
        $userInfo   = UserService::getInstance($userId)->get();
        // 获取用户所属的部门
        $data['dept_id'] = isset($data['dept_id']) ? $data['dept_id'] : $userInfo['dept_id'];
        $data['need_appr'] = self::calNeedAppr($data) ? 1 : 0;        
        //提交审批
        if ($data['need_appr']) {
            $data['approval_thing_id'] = self::addApprRam($data);
        }
    }

    /**
     * 生成
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterSave(&$data, $uuid) {
        // 20230724：尝试调整订单为多来源
        // self::getInstance($uuid)->generateOrderGetId();
        $feeArr = Arrays::value($data, 'feeArr', []);
        if ($feeArr) {
            $feeList = [];
            foreach ($feeArr as $k => $v) {
                if (!$v) {
                    //没有金额不处理
                    continue;
                }
                $tmpData = [];
                $tmpData['fee_id'] = $uuid;
                $tmpData['fee_type'] = $k;
                // 20230629：车辆信息
                $tmpData['bus_id'] = Arrays::value($data, 'bus_id');
                $tmpData['money'] = $v;
                $feeList[] = $tmpData;
            }
            FinanceStaffFeeListService::saveAllRam($feeList);
            self::getInstance($uuid)->feeMoneyUpdateRam();
        }
    }

    /**
     * 更新后触发器
     * @param type $data
     * @param type $uuid
     */
    public static function ramPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        $data['orderChange'] = isset($data['order_id']) && $data['order_id'] != Arrays::value($info, 'order_id');
    }

    public static function ramAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
//        if(isset($data['orderChange']) && !$data['order_id']){
//            //删单状态
//            OrderService::getInstance($info['order_id'])->objAttrsUnSet('financeStaffFee',$uuid);
//        }
        //20220623用于触发更新
        $orderId = Arrays::value($info, 'order_id');
        if ($orderId) {
            OrderService::getInstance($orderId)->objAttrsUpdate('financeStaffFee', $uuid, $info);
            OrderService::getInstance($orderId)->updateRam(['status' => 1]);
        }
        //20221007
        $feeArr = Arrays::value($data, 'feeArr', []);
        if ($feeArr) {
            foreach ($feeArr as $k => $v) {
                $tmpData = [];
                $tmpData['fee_id'] = $uuid;
                $tmpData['fee_type'] = $k;
                $tmpData['money'] = $v;

                $con = [];
                $con[] = ['fee_id', '=', $uuid];
                $con[] = ['fee_type', '=', $k];
                $info = FinanceStaffFeeListService::mainModel()->where($con)->find();
                if ($info) {
                    //更新
                    FinanceStaffFeeListService::getInstance($info['id'])->updateRam($tmpData);
                } else if ($v) {
                    //新增
                    FinanceStaffFeeListService::saveRam($tmpData);
                }
            }
        }
    }

    public function ramPreDelete() {
        $info = $this->get();
        if ($info['has_settle']) {
            throw new Exception('已支付不可删');
        }
        if ($this->uuid) {
            $con[] = ['fee_id', '=', $this->uuid];
            $ids = FinanceStaffFeeListService::mainModel()->where($con)->column('id');
            foreach ($ids as $id) {
                FinanceStaffFeeListService::getInstance($id)->deleteRam();
            }
        }
        //20220624关联删订单
        if ($info['order_id']) {
            OrderService::getInstance($info['order_id'])->deleteRam();
        }

        //删除关联的审批记录
        if ($info['approval_thing_id']) {
            ApprovalThingService::getInstance($info['approval_thing_id'])->deleteRam();
        }
    }

    public function doAddAppr() {
        return $this->approvalAdd();
    }

    /**
     * 20230704:接口规范写法
     * 20230729:ram写法
     * @return type
     */
    public function approvalAdd() {
        $infoArr = $this->get();
        // $infoArr    = $info ? $info->toArray() : [];
        $exiApprId = ApprovalThingService::belongTableIdToId($this->uuid);
        //已有直接写，没有的加审批
        $data['approval_thing_id'] = $exiApprId ?: self::addApprRam($infoArr);
        return $this->updateRam($data);
    }

    /**
     * 事项提交去审批
     */
    protected static function addApprRam($data) {
        $sData = Arrays::getByKeys($data, ['user_id']);
        $sData['belong_table'] = self::getTable();
        $sData['belong_table_id'] = $data['id'];
        $sData['dept_id'] = $data['dept_id'];
        $sData['userName'] = UserService::getInstance($data['user_id'])->fRealName();
        // 审批事项
        return ApprovalThingService::thingCateAddApprRam('staffFee', $data['user_id'], $sData);
    }

    /**
     * 20320726:订单账单添加
     */
    public function addStatementOrder() {
        $info = $this->get();
        if (!$info) {
            throw new Exception('报销记录不存在' . $this->uuid);
        }

        $prizeKey = 'staffFee';
        $belongTable = self::getTable();

        $data['user_id'] = $info['user_id'];
        $res = FinanceStatementOrderService::belongTablePrizeKeySaveRam($prizeKey, $info['money'], $belongTable, $this->uuid, $data);
        return $res;
    }

    /**
     * 额外详情
     * @param type $ids
     * @return type
     */
    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function ($lists) use ($ids) {

                    $cond[] = ['fee_id', 'in', $ids];
                    $feeListsObj = FinanceStaffFeeListService::where($cond)->select();
                    $feeLists = $feeListsObj ? $feeListsObj->toArray() : [];

                    foreach ($lists as &$v) {
                        //是否刚添加的记录,4小时内
                        $v['isRecent'] = time() > strtotime($v['create_time']) && (time() - strtotime($v['create_time'])) < 3600 * 4 ? 1 : 0;
                        //驾驶员
                        $con = [];
                        $con[] = ['fee_id', '=', $v['id']];
                        $feeArr = Arrays2d::listFilter($feeLists, $con);
                        $v['feeArr'] = array_column($feeArr, 'money', 'fee_type');
                        // 20230418：明细数
                        $v['feeListCount'] = count($feeArr);
                        $v['feeListSum'] = array_sum(array_column($feeArr, 'money'));
                    }

                    return $lists;
                }, true);
    }

    /**
     * 更新费用金额
     * 一般用于写入明细后更新总额
     */
    public function feeMoneyUpdate() {
        $con[] = ['fee_id', '=', $this->uuid];
        $money = FinanceStaffFeeListService::mainModel()->where($con)->sum('money');
        $data['money'] = $money;
        return $this->update($data);
    }

    public function feeMoneyUpdateRam() {
        $data['money'] = $this->calFeeMoney();
        return $this->updateRam($data);
    }

    /**
     * 20220623:计算佣金总额
     */
    public function calFeeMoney() {
        $lists = $this->objAttrsList('financeStaffFeeList');
        // dump($lists);
        return array_sum(array_column($lists, 'money'));
    }

    /**
     * 20230425:计算当前的用车申请是否需要审批
     */
    public static function calNeedAppr($data) {

        // 默认是需要审批的
        return true;
    }

    /**
     * 20230731:
     * 报销人报销预处理数据
     */
    public static function findFeePreData(){
        $data['user_id']    = session(SESSION_USER_ID);
        $data['apply_time'] = date('Y-m-d H:i:s');
        
        //有车跳车，没车跳最后一条
        $con[] = ['current_driver','=',session(SESSION_USER_ID)];
        $busId = BusService::mainModel()->where($con)->value('id');
        
        $data['bus_id']     = $busId;
        return $data;
    }
    
    /**
     * 会计状态：0待审批；1已同意，2已拒绝
     */
    public function fAccStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单号
     * @return type
     */
    public function fOrderId() {
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

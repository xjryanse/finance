<?php

namespace xjryanse\finance\service\staffFee;

use xjryanse\approval\service\ApprovalThingService;
use xjryanse\order\service\OrderService;
use xjryanse\user\service\UserService;
use xjryanse\finance\service\FinanceStaffFeeListService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Debug;
use xjryanse\logic\Strings;
use Exception;

/**
 * 
 */
trait TriggerTraits{

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

    public static function ramPreSave(&$data, $uuid) {
        $keys               = ['user_id'];
        $notice['user_id']  = '报销人必须';
        $notice['feeArr']   = '报销项目必填';
        DataCheck::must($data, $keys, $notice);
        // 20230730校验报销项目金额是否大于0 
        if(isset($data['feeArr']) && array_sum(array_values($data['feeArr'])) == 0){
            throw new Exception('报销金额不能为0');
        }

        $userId     = Arrays::value($data, 'user_id');
        $userInfo   = UserService::getInstance($userId)->get();
        $orderId    = Arrays::value($data, 'order_id');
        if ($orderId) {
            $data['dept_id'] = OrderService::getInstance($orderId)->fDeptId();
        }
        // 获取用户所属的部门
        $data['dept_id']    = isset($data['dept_id']) ? $data['dept_id'] : $userInfo['dept_id'];
        $data['need_appr']  = self::calNeedAppr($data) ? 1 : 0;
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
                if (!intval($v)) {
                    //没有金额不处理:20240116:处理0.00
                    continue;
                }
                $tmpData = [];
                $tmpData['fee_id']      = $uuid;
                if(Strings::isSnowId($k)){
                    $tmpData['fee_type_id'] = $k;
                } else {
                    $tmpData['fee_type'] = $k;
                }
                // 20230629：车辆信息
                $tmpData['bus_id']      = Arrays::value($data, 'bus_id');
                $tmpData['money']       = $v;
                $feeList[]              = $tmpData;
            }

            
            FinanceStaffFeeListService::saveAllRam($feeList);

            // dump(self::getInstance($uuid)->objAttrsList('financeStaffFeeList'));

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
                $tmpData['money'] = $v;

                $con = [];
                if(Strings::isSnowId($k)){
                    $tmpData['fee_type_id'] = $k;
                    $con[] = ['fee_type_id', '=', $k];
                } else {
                    $tmpData['fee_type'] = $k;
                    $con[] = ['fee_type', '=', $k];
                }
                if(isset($data['bus_id'])){
                    // 20230629：车辆信息
                    $tmpData['bus_id']      = Arrays::value($data, 'bus_id');
                }
                
                $con[] = ['fee_id', '=', $uuid];
                $info = FinanceStaffFeeListService::mainModel()->where($con)->find();
                if ($info) {
                    // 20240116；空的删了
                    if(!intval($v)){
                        FinanceStaffFeeListService::getInstance($info['id'])->deleteRam();
                    } else {
                        //更新
                        FinanceStaffFeeListService::getInstance($info['id'])->updateRam($tmpData);
                    }
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
        //20220624关联删订单:规则调整，不能删了
        // if ($info['order_id']) {
            // OrderService::getInstance($info['order_id'])->deleteRam();
        // }

        //删除关联的审批记录
        if ($info['approval_thing_id']) {
            ApprovalThingService::getInstance($info['approval_thing_id'])->deleteRam();
        }
        // 2024-01-16删除关联的账单明细
        $conSt[] = ['belong_table_id','=',$this->uuid];
        $conSt[] = ['belong_table','=',self::getTable()];
        $statementOrders = FinanceStatementOrderService::where($conSt)->select();
        foreach($statementOrders as $v){
            if($v['has_statement']){
                throw new Exception('付款处理中不可操作，如需删除请联系后台处理');
            }
            // 删除
            FinanceStatementOrderService::getInstance($v['id'])->deleteRam();
        }
    }
}

<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Number;
use xjryanse\order\service\OrderService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\customer\service\CustomerUserService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\finance\service\FinanceManageAccountLogService;
use xjryanse\finance\logic\UserPayLogic;
use xjryanse\wechat\service\WechatWxPayConfigService;
use xjryanse\wechat\service\WechatWxPayLogService;
use xjryanse\wechat\service\WechatWxPayRefundLogService;
use xjryanse\wechat\WxPay\v2\WxPayApiXie;
use think\Db;
use Exception;

/**
 * 收款单-订单关联
 */
class FinanceStatementService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatement';
    //直接执行后续触发动作
    protected static $directAfter = true;
    ///从ObjectAttrTrait中来
    // 定义对象的属性
    protected $objAttrs = [];
    // 定义对象是否查询过的属性
    protected $hasObjAttrQuery = [];
    // 定义对象属性的配置数组
    protected static $objAttrConf = [
//        'financeStatementOrder' => [
//            'class' => '\\xjryanse\\finance\\service\\FinanceStatementOrderService',
//            'keyField' => 'statement_id',
//            'master' => true
//        ],
//        'financeAccountLog' => [
//            'class' => '\\xjryanse\\finance\\service\\FinanceAccountLogService',
//            'keyField' => 'statement_id',
//            'master' => true
//        ]
    ];
    
    // 20230710：开启方法调用统计
    protected static $callStatics = true;

    use \xjryanse\finance\service\statement\TriggerTraits;
    use \xjryanse\finance\service\statement\FieldTraits;
    
    /**
     * 获取账单未结清的金额
     * 一般用于组合支付中进行业务逻辑处理
     */
    public function remainMoney() {
        $info = $this->get();
        // 总金额
        $totalMoney = $info['need_pay_prize'];
        // 已结清金额
        $finishMoney = FinanceAccountLogService::statementFinishMoney($this->uuid);
        // -300 -50
        // 解决浮点数运算TODO更优？
        return (intval($totalMoney * 100) - intval($finishMoney * 100)) * 0.01;
    }

    /**
     * 直冲用户账户（微信分账，用户余额）
     * 
     */
    public function doDirect() {
        $con = [];
        $con[] = ['statement_id', '=', $this->uuid];
        $statementOrderInfoCount = FinanceStatementOrderService::count($con);
        if (!$statementOrderInfoCount) {
            throw new Exception("账单" . $this->uuid . "没有账单明细");
        }
        if ($statementOrderInfoCount > 1) {
            throw new Exception("不支持多明细账单直接结算" . $this->uuid . "共计" . $statementOrderInfoCount . "笔");
        }
        $statementOrderInfo = FinanceStatementOrderService::find($con);
        $goodsPrizeInfo = GoodsPrizeKeyService::getByPrizeKey(Arrays::value($statementOrderInfo, 'statement_type'));  //价格key取归属
        //如果是充值到余额的，直接处理
        if (Arrays::value($goodsPrizeInfo, 'to_money') == "money") {
            //收款操作
            return UserPayLogic::collect($this->uuid, FR_FINANCE_MONEY);
        }
        //如果是分账的，也直接处理
        if (Arrays::value($goodsPrizeInfo, 'to_money') == "sec_share") {
            return UserPayLogic::secCollect($this->uuid, FR_FINANCE_WECHAT);
        }
    }

    /**
     * 清除未处理的账单
     * 一般用于订单取消，撤销全部的订单
     * ！！【未测】20210402
     */
    public static function clearOrderNoDeal($orderId) {
        Debug::debug(__CLASS__ . __FUNCTION__, $orderId);
        self::checkTransaction();
        if (!$orderId) {
            throw new Exception('订单id必须');
        }
        //20220311:账单id从明细取
//        $cond[] = ['order_id','in',$orderId];
//        $statementIds = FinanceStatementOrderService::mainModel()->where($cond)->column('statement_id');
        // 20221120
        $statementOrders = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $statementIds = array_column($statementOrders, 'statement_id');

        $con[] = ['id', 'in', $statementIds];
        //20220311到这里
        // $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle', '=', 0];      //未结算
        $lists = self::mainModel()->where($con)->select();
        Debug::debug(__CLASS__ . __FUNCTION__ . '的$con', $con);
        Debug::debug(__CLASS__ . __FUNCTION__ . '的$lists', $lists);
        foreach ($lists as $k => $v) {
            //设为未对账
            self::getInstance($v['id'])->update(['has_confirm' => 0]);
            //然后才能删
            self::getInstance($v['id'])->delete();
        }
    }

    /**
     * 20220622
     * @param type $orderId
     * @throws Exception
     */
    public static function clearOrderNoDealRam($orderId) {
        if (!$orderId) {
            throw new Exception('订单id必须');
        }
        //20220311:账单id从明细取
        // $cond[] = ['order_id','in',$orderId];
        // $statementIds = FinanceStatementOrderService::mainModel()->where($cond)->column('statement_id');
        $statementOrders = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $statementIds = array_column($statementOrders, 'statement_id');
        $con[] = ['id', 'in', $statementIds];
        //20220311到这里
        // $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle', '=', 0];      //未结算
        $lists = self::mainModel()->where($con)->select();
        foreach ($lists as $k => $v) {
            //设为未对账
            self::getInstance($v['id'])->updateRam(['has_confirm' => 0]);
            //然后才能删
            self::getInstance($v['id'])->deleteRam();
        }
    }

    /**
     * 根据订单明细id，生成账单
     * @param type $statementOrderIds   账单订单表的id
     * @return type
     */
    public static function statementGenerate($statementOrderIds = []) {
        //self::checkNoTransaction();
        //字符串数组都可以传
        $data['statementOrderIds'] = $statementOrderIds ? (is_array($statementOrderIds) ? $statementOrderIds : [$statementOrderIds]) : [];
        $data['has_confirm'] = 1;    //默认已确认
        $orderIds = FinanceStatementOrderService::column('distinct order_id', [['id', 'in', $statementOrderIds]]);
        $source = OrderService::mainModel()->where([['id', 'in', $orderIds]])->column('distinct source');
        if (in_array('admin', $source)) {
            $data['group'] = 'offline'; //线下
        } else {
            $data['group'] = 'online';  //线上
        }
        Db::startTrans();
        $res = FinanceStatementService::save($data);
        Db::commit();

        return $res;
    }

    
    /**
     * 20230903:增加的ram方法
     * 根据订单明细id，生成账单
     * @param type $statementOrderIds   账单订单表的id
     * @return type
     */
    public static function statementGenerateRam($statementOrderIds = []) {
        //self::checkNoTransaction();
        //字符串数组都可以传
        $data['statementOrderIds'] = $statementOrderIds 
                ? (is_array($statementOrderIds) ? $statementOrderIds : [$statementOrderIds]) 
                : [];
        $data['has_confirm'] = 1;    //默认已确认
//        $orderIds = FinanceStatementOrderService::column('distinct order_id', [['id', 'in', $statementOrderIds]]);
//        $source = OrderService::mainModel()->where([['id', 'in', $orderIds]])->column('distinct source');
//        if (in_array('admin', $source)) {
//            $data['group'] = 'offline'; //线下
//        } else {
//            $data['group'] = 'online';  //线上
//        }
//        Db::startTrans();
        $res = FinanceStatementService::saveRam($data);
//        Db::commit();

        return $res;
    }
    
    /**
     * 单订单生成对账单名称
     * @param type $orderId
     */
    public static function getStatementNameByOrderId($orderId, $statementType) {
        //商品名称加上价格的名称
        $orderInfo = OrderService::getInstance($orderId)->get(0);
        $fGoodsCate = Arrays::value($orderInfo, 'goods_cate');
        $statementName = $fGoodsCate ? $fGoodsCate . '-' : '';
        $fGoodsName = Arrays::value($orderInfo, 'goods_name');
        $goodsPrizeInfo = GoodsPrizeKeyService::getByPrizeKey($statementType);
        $statementName .= $fGoodsName . " " . Arrays::value($goodsPrizeInfo, 'prize_name');
        return $statementName;
    }

    /**
     * 获取账号id
     */
    public function getAccountId() {
        $con[] = ['statement_id', '=', $this->uuid];
        return FinanceAccountLogService::mainModel()->where($con)->value('account_id');
    }
//    /**
//     * 弃用
//     * @param array $item
//     * @param type $uuid
//     * @return bool
//     */
//    public static function extraDetail(&$item, $uuid) {
//        if (!$item) {
//            return false;
//        }
//        $manageAccountId = Arrays::value($item, 'manage_account_id');
//        //管理账户余额
//        $info = FinanceManageAccountService::getInstance($manageAccountId)->get(0);
//        $item['manageAccountMoney'] = Arrays::value($info, 'money');
//        //合同订单:逗号分隔
//        $con[] = ['statement_id', '=', $uuid];
//        $orderIds = FinanceStatementOrderService::mainModel()->where($con)->column('order_id');
//        $item->SCorder_id = count($orderIds);         //订单数量
//        $item->Dorder_id = implode(',', $orderIds);  //订单逗号分隔
//
//        return $item;
//    }

    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {
                    $statementOrderCountArr = FinanceStatementOrderService::groupBatchCount('statement_id', $ids);
                    // 20230814:统计原价
                    $originalSumArr         = FinanceStatementOrderService::groupBatchSum('statement_id', $ids, 'original_prize');
                    //微信支付数
                    $wxPayLogArr            = WechatWxPayLogService::groupBatchCount('statement_id', $ids);
                    //微信退款数
                    $wxPayRefLogArr         = WechatWxPayRefundLogService::groupBatchCount('out_refund_no', $ids);

                    $manageAccountLogCount  = FinanceManageAccountLogService::groupBatchCount('from_table_id', $ids);
                    // 20230905：关联的退款记录数
                    $refCountArr            = self::groupBatchCount('ref_statement_id', $ids);
                    $refSumArr              = self::groupBatchSum('ref_statement_id', $ids, 'need_pay_prize',[['has_settle','=','1']]);

                    foreach ($lists as &$v) {
                        //明细数
                        $v['statementOrderCount']   = Arrays::value($statementOrderCountArr, $v['id'], 0);
                        $original                   = Arrays::value($originalSumArr, $v['id'], 0);
                        // 20230814:原价
                        $v['originalPrize']         = $original;
                        $v['discountPrize']         = $original ?  Number::minus($original, $v['need_pay_prize']) : '';

                        //20220609:是否有订单号；用于控制按钮显示
                        $v['hasOrderId']            = $v['order_id'] ? 1 : 0;
                        // 微信支付数
                        $v['wxPayLogCount']         = Arrays::value($wxPayLogArr, $v['id'], 0);
                        // 微信退款数
                        $v['wxRefLogCount']         = Arrays::value($wxPayRefLogArr, $v['id'], 0);
                        // 20230726 冲账记录数:0未冲账；1已冲账
                        $v['manageLogCount']        = Arrays::value($manageAccountLogCount, $v['id'], 0);
                        // 20230905:后向退款账单笔数
                        $v['aftRefStatementCount']  = Arrays::value($refCountArr, $v['id'], 0);
                        // 20230905:后向已退款金额
                        $v['aftRefedPrize']         = Arrays::value($refSumArr, $v['id'], 0);
                        // 20230905:剩余金额:替代remainPrize字段
                        $v['finalPrize']            = $v['need_pay_prize'] + $v['aftRefedPrize'] ;
                    }

                    return $lists;
                }, true);
    }

    public static function save($data) {
        self::checkTransaction();
        //无单条订单，无订单数组
        if (!Arrays::value($data, 'order_id') && !Arrays::value($data, 'orders') && !Arrays::value($data, 'statementOrderIds')) {
            throw new Exception('请选择订单');
        }
        //转为数组存
        if (is_string(Arrays::value($data, 'order_id')) && Arrays::value($data, 'order_id')) {
            $cateKey = Arrays::value($data, 'statement_type');
            $data['statement_name'] = self::getStatementNameByOrderId($data['order_id'], $cateKey);
        }
        $res = self::commSave($data);
        //转为数组存
        if (is_string(Arrays::value($data, 'order_id')) && Arrays::value($data, 'order_id')) {
            //单笔订单的存法
            $data['orders'] = [$data];
        }
        //【TODO优化2021-03-03】创建对账订单明细。
        if (isset($data['orders'])) {
            foreach ($data['orders'] as &$value) {
                $value['belong_cate'] = Arrays::value($res, 'belong_cate');
                $value['user_id'] = Arrays::value($res, 'user_id');
                $value['manage_account_id'] = Arrays::value($res, 'manage_account_id');
                $value['customer_id'] = Arrays::value($res, 'customer_id');
                $value['statement_id'] = $res['id'];
                $value['statement_cate'] = Arrays::value($res, 'statement_cate');
                //            if(FinanceStatementOrderService::hasStatement( $customerId, $value['order_id'] )){
                //                throw new Exception('订单'.$value['order_id'] .'已经对账过了');
                //            }
                //一个一个添，有涉及其他表的状态更新
                FinanceStatementOrderService::save($value);
            }
        }
        return $res;
    }

    /**
     * 对冲结算逻辑
     * FinanceManageAccountLog登记一笔冲账记录
     * 更新本表has_settle 字段为1；
     * 更新FinanceStatementOrder的has_settle字段为1；
     */
    protected function settle($accountLogId = '') {
        self::checkTransaction();
        if (FinanceManageAccountLogService::hasLog(self::mainModel()->getTable(), $this->uuid)) {
            return false;
        }
        $info = $this->get();
        Debug::debug('settle的info', $info);
        $hasConfirm = Arrays::value($info, 'has_confirm');  //客户已确认
        if (!$hasConfirm) {
            throw new Exception('请先进行客户确认，才能冲账，对账单号:' . $this->uuid);
        }
        $customerId = Arrays::value($info, 'customer_id');
        $userId = Arrays::value($info, 'user_id');
        $needPayPrize = Arrays::value($info, 'need_pay_prize');   //正-他欠我，负-我欠他
        //扣减对冲账户余额
        if ($customerId) {
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $manageAccountInfo = FinanceManageAccountService::getInstance($manageAccountId)->get(0);
        $manageAccountMoney = Arrays::value($manageAccountInfo, 'money');   //账户余额

        if (!$accountLogId) {
            $con = [];
            $con[] = ['statement_id', '=', $this->uuid];
            $accountLog = FinanceAccountLogService::find($con);
            $accountLogId = Arrays::value($accountLog, 'id');
        }

        if ($needPayPrize > 0) {
            $data['change_type'] = 2;   //客户出账，我进账，客户金额越来越少
            if ($manageAccountMoney < $needPayPrize && !$accountLogId) {
                throw new Exception('客户账户余额(￥' . $manageAccountMoney . ')不足，请先收款');
            }
        } else {
            $data['change_type'] = 1;   //客户进账，我出账，客户金额越来越多
            if ($manageAccountMoney > $needPayPrize && !$accountLogId) {
                throw new Exception('该客户当前已付款(￥' . abs($manageAccountMoney) . ')不足，请先付款');
            }
        }
        $data['manage_account_id'] = $manageAccountId;
        $data['money'] = Arrays::value($info, 'need_pay_prize');
        $data['from_table'] = self::mainModel()->getTable();
        $data['from_table_id'] = $this->uuid;
        $data['reason'] = Arrays::value($info, 'statement_name') . ' 冲账';
        //登记冲账
        FinanceManageAccountLogService::save($data);
        $stateData['has_settle'] = 1;
        if ($accountLogId) {
            $stateData['account_log_id'] = $accountLogId;
            $stateData['account_bill_time'] = FinanceAccountLogService::getInstance($accountLogId)->fBillTime();
        }
        $res = self::mainModel()->where('id', $this->uuid)->update($stateData);   //更新为已结算
//        //冗余：20220617，似乎可以取消？？？
        $con[] = ['statement_id', '=', $this->uuid];
        $lists = FinanceStatementOrderService::lists($con);
        foreach ($lists as $v) {
            FinanceStatementOrderService::getInstance($v['id'])->update(['has_settle' => 1]);   //更新为已结算
        }
        //20220516，使用上述方法在ydzb有性能问题，下述方法无法触发更新。TODO，寻求更优解决方案？？
//        $con[] = ['statement_id','=',$this->uuid ];
//        FinanceStatementOrderService::mainModel()->where($con)->update(['has_settle'=>1]);        
        return $res;
    }

    /**
     * 20220620
     * @param type $accountLogId
     * @return boolean
     * @throws Exception
     */
    protected function settleRam($accountLogId = '') {
        if (FinanceManageAccountLogService::hasLog(self::mainModel()->getTable(), $this->uuid)) {
            return false;
        }
        $info = $this->get();
//        throw new Exception('测试');
//        dump($info);exit;
        Debug::debug('settleRam的info', $info);
        $hasConfirm = Arrays::value($info, 'has_confirm');  //客户已确认
        if (!$hasConfirm) {
            // 20230906???前端报错
            // throw new Exception('请先进行客户确认，才能冲账，对账单号:' . $this->uuid);
        }
        $customerId = Arrays::value($info, 'customer_id');
        $userId = Arrays::value($info, 'user_id');
        $needPayPrize = Arrays::value($info, 'need_pay_prize');   //正-他欠我，负-我欠他
        //扣减对冲账户余额
        if ($customerId) {
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $manageAccountInfo = FinanceManageAccountService::getInstance($manageAccountId)->get(0);
        $manageAccountMoney = Arrays::value($manageAccountInfo, 'money');   //账户余额

        if (!$accountLogId) {
            $con = [];
            $con[] = ['statement_id', '=', $this->uuid];
            $accountLog = FinanceAccountLogService::find($con);
            $accountLogId = Arrays::value($accountLog, 'id');
        }

        if ($needPayPrize > 0) {
            $data['change_type'] = 2;   //客户出账，我进账，客户金额越来越少
            if ($manageAccountMoney < $needPayPrize && !$accountLogId) {
                //20220622:内部调费用有bug
                //throw new Exception('客户账户余额(￥'. $manageAccountMoney  .')不足，请先收款');
            }
        } else {
            $data['change_type'] = 1;   //客户进账，我出账，客户金额越来越多
            if ($manageAccountMoney > $needPayPrize && !$accountLogId) {
                //20220622:内部调费用有bug
                //throw new Exception('该客户当前已付款(￥'. abs($manageAccountMoney)  .')不足，请先付款');
            }
        }
        $data['manage_account_id'] = $manageAccountId;
        $data['money'] = Arrays::value($info, 'need_pay_prize');
        $data['from_table'] = self::mainModel()->getTable();
        $data['from_table_id'] = $this->uuid;
        $data['reason'] = Arrays::value($info, 'statement_name') . ' 冲账';
        //登记冲账
        FinanceManageAccountLogService::saveRam($data);
        $stateData['has_settle'] = 1;
        if ($accountLogId) {
            $stateData['account_log_id'] = $accountLogId;
            $stateData['account_bill_time'] = FinanceAccountLogService::getInstance($accountLogId)->fBillTime();
        }
        //$res = self::mainModel()->where('id',$this->uuid)->update( $stateData );   //更新为已结算
        $res = $this->updateRam($stateData);
//        //冗余：20220617，似乎可以取消？？？
        $lists = $this->objAttrsList('financeStatementOrder');
        foreach ($lists as $v) {
            FinanceStatementOrderService::getInstance($v['id'])->updateRam(['has_settle' => 1]);   //更新为已结算
        }
        //20220516，使用上述方法在ydzb有性能问题，下述方法无法触发更新。TODO，寻求更优解决方案？？
//        $con[] = ['statement_id','=',$this->uuid ];
//        FinanceStatementOrderService::mainModel()->where($con)->update(['has_settle'=>1]);        
        return $res;
    }

    /**
     * 取消对冲结算逻辑
     */
    protected function cancelSettle() {
        self::checkTransaction();
        $con[] = ['from_table', '=', self::mainModel()->getTable()];
        $con[] = ['from_table_id', '=', $this->uuid];
        $lists = FinanceManageAccountLogService::lists($con);
        foreach ($lists as $v) {
            //一个个删，可能关联其他的删除
            FinanceManageAccountLogService::getInstance($v['id'])->delete();
        }
        //步骤2：
        $res = self::mainModel()->where('id', $this->uuid)->update(['has_settle' => 0, "account_log_id" => "", "account_bill_time" => null]);   //更新为未结算
        //20220516：调整，以触发触发器，TODO解决性能问题 20220617，似乎可以取消？？？
        $cone[] = ['statement_id', '=', $this->uuid];
        $listsOrder = FinanceStatementOrderService::lists($cone);
        foreach ($listsOrder as $v) {
            FinanceStatementOrderService::getInstance($v['id'])->update(['has_settle' => 0]);   //更新为未结算
        }
        //20220516注释
        //FinanceStatementOrderService::mainModel()->where('statement_id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        //步骤3：【关联删入账】20210319关联删入账
        $con2[] = ['statement_id', '=', $this->uuid];
        $listsAccountLog = FinanceAccountLogService::lists($con2);
        foreach ($listsAccountLog as $v) {
            //一个个删，可能关联其他的删除
            FinanceAccountLogService::getInstance($v['id'])->delete();
        }
        return $res;
    }

    /**
     * 取消对冲结算逻辑
     */
    protected function cancelSettleRam() {
        $con[] = ['from_table', '=', self::mainModel()->getTable()];
        $con[] = ['from_table_id', '=', $this->uuid];
        $lists = FinanceManageAccountLogService::lists($con);
        foreach ($lists as $v) {
            //一个个删，可能关联其他的删除
            FinanceManageAccountLogService::getInstance($v['id'])->deleteRam();
        }
        //步骤2：
        //$res = self::mainModel()->where('id',$this->uuid)->update(['has_settle'=>0,"account_log_id"=>""]);   //更新为未结算
        $res = $this->updateRam(['has_settle' => 0, "account_log_id" => "", "account_bill_time" => null]);
        //20220516：调整，以触发触发器，TODO解决性能问题 20220617，似乎可以取消？？？
        $cone[] = ['statement_id', '=', $this->uuid];
        $listsOrder = FinanceStatementOrderService::lists($cone);
        foreach ($listsOrder as $v) {
            FinanceStatementOrderService::getInstance($v['id'])->updateRam(['has_settle' => 0]);   //更新为未结算
        }
        //20220516注释
        //FinanceStatementOrderService::mainModel()->where('statement_id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        //步骤3：【关联删入账】20210319关联删入账
        $con2[] = ['statement_id', '=', $this->uuid];
        $listsAccountLog = FinanceAccountLogService::lists($con2);
        foreach ($listsAccountLog as $v) {
            //一个个删，可能关联其他的删除
            FinanceAccountLogService::getInstance($v['id'])->deleteRam();
        }
        return $res;
    }

    /**
     * todo:改用ram方法替代
     * 退款账单自动关联一笔付款账单
     * 获取当前账单id，若当前账单已关联退款单，或不是退款账单，则返回
     * 当前账单有多笔订单，不支持关联原始账单 - ？？？
     * 
     */
    public function refUni() {
        $info = $this->get();
        Debug::debug('退款账单信息', $info);
        if ($info['ref_statement_id']) {
            return false;
        }
        if (!$info['is_ref']) {
            throw new Exception($this->uuid . '不是退款账单');
        }
        if (!$info['order_id']) {
            throw new Exception('仅支持单订单账单');
        }
        //20220311:账单id从明细取
//        $cond[] = ['order_id','=',$info['order_id']];
//        $statementIds = FinanceStatementOrderService::mainModel()->where($cond)->column('statement_id');
        $statementOrders = OrderService::getInstance($info['order_id'])->objAttrsList('financeStatementOrder');
        $statementIds = array_column($statementOrders, 'statement_id');

        $con[] = ['id', 'in', $statementIds];
        //剩余金额大于等于当前账单金额，且金额从小到大排列，取第一条记录
        // $con[] = ['order_id','=',$info['order_id']];
        $con[] = ['is_ref', '=', 0];  //非退款订单
        $con[] = ['statement_cate', '=', $info['statement_cate']];
        $con[] = ['remainPrize', '>=', $info['need_pay_prize']];      //虚拟计算字段，版本5.7以上
        $incomeInfo = self::mainModel()->where($con)->find();
        if (!$incomeInfo) {
            throw new Exception('没有匹配的付款账单');
        }
        $data['ref_statement_id'] = $incomeInfo['id'];
        $this->update($data);
    }
    /**
     * 20230905 关联退款账单
     */
    public function refUniRam(){
        $info = $this->get();
        // 已关联，或不是退款账单,不处理
        if ($info['ref_statement_id'] || !$info['is_ref']) {
            return false;
        }
        // 非单笔订单，不处理
        $stOrders = $this->objAttrsList('financeStatementOrder');
        if(count($stOrders) > 1){
            throw new Exception('原路退款仅支持单订单明细' . $this->uuid );
        }
        $statementOrders = OrderService::getInstance($info['order_id'])->objAttrsList('financeStatementOrder');
        $statementIds = array_column($statementOrders, 'statement_id');
        
        $con = [];
        $con[] = ['id', 'in', $statementIds];
        //剩余金额大于等于当前账单金额，且金额从小到大排列，取第一条记录
        // $con[] = ['order_id','=',$info['order_id']];
        $con[] = ['is_ref', '=', 0];  //非退款订单
        $con[] = ['statement_cate', '=', $info['statement_cate']];
        $con[] = ['remainPrize', '>=', $info['need_pay_prize']];      //虚拟计算字段，版本5.7以上
        $incomeInfo = self::mainModel()->where($con)->find();
        if (!$incomeInfo) {
            throw new Exception('没有匹配的付款账单:'.$this->uuid);
        }

        $data = [];
        $data['ref_statement_id'] = $incomeInfo['id'];
        $this->updateRam($data);
    }
    
    /**
     * 20230905：原路退款返回
     */
    public function doRefRevert(){
        // 20230905:TODO可拆分？？
        $this->refUniRam();
        // 待退款账单
        $info               = $this->get();

        $stOrders = $this->objAttrsList('financeStatementOrder');
        if(count($stOrders) > 1){
            throw new Exception('原路退款仅支持单订单明细' . $this->uuid );
        }
        // 提取原始账单
        $rawStatementId     = Arrays::value($info, 'ref_statement_id');
        if (!$rawStatementId) {
            throw new Exception('没有关联的原付款账单' . $this->uuid );
        }
        $rawStatementInfo   = self::getInstance($rawStatementId)->get(0);
        if (!$rawStatementInfo['account_log_id']) {
            throw new Exception('原付款账单无支付流水记录' . $this->uuid );
        }
        // 提取原付款单的支付类型
        $payBy = Arrays::value($rawStatementInfo, 'pay_by');
        if(!$payBy){
            throw new Exception('原付款账单支付来源不详:'.$this->uuid );
        }
        //原付款单的支付记录
        // payBy的值：FR_FINANCE_WECHAT；FR_FINANCE_MONEY；FR_FINANCE_CMBSKT；FR_FINANCE_WXWORK；
        $res = UserPayLogic::ref($this->uuid, $payBy);
        dump('退款结果');
        dump($res);
    }

    /**
     * 通过微信渠道进行退款；
     * 需要原付款单通过微信支付
     * 20230905：改写成支持多来源渠道的退款 doRefRevert ，此方法逐步弃用
     */
    public function refWxPay() {
        //关联一下付款账单
        Db::startTrans();
        $this->refUni();
        Db::commit();
        //从主库读取数据
        $info = self::mainModel()->master()->get($this->uuid);
        if (!$info['order_id']) {
            throw new Exception('线上退款仅支持单订单，请重新生成对账单');
        }
        if (!$info['ref_statement_id']) {
            throw new Exception($this->uuid . '未指定原付款账单');
        }
        $payInfo = self::getInstance($info['ref_statement_id'])->get(0);
        if (!$payInfo['account_log_id']) {
            throw new Exception($this->uuid . '原付款账单无支付流水记录');
        }
        //原付款单的支付记录
        $payLogInfo = FinanceAccountLogService::getInstance($payInfo['account_log_id'])->get(0);
        if ($payLogInfo['from_table'] != "w_wechat_wx_pay_log") {
            throw new Exception('原付款账单非微信支付，不可使用微信支付渠道退款');
        }
        $con = [];
        $con[] = ['company_id', '=', $info['company_id']];
        //$payConf = WechatWxPayConfigService::mainModel()->where( $con )->find();
        $payConf = WechatWxPayConfigService::getByCompanyId($info['company_id']);
        Debug::debug('$payConf', $payConf);
        //TODO待调整20210225
        $thirdPayParam['wePubAppId'] = Arrays::value($payConf, 'AppId');
        $thirdPayParam['openid'] = WechatWxPayLogService::getInstance($payLogInfo['from_table_id'])->fOpenid();
        //国通支付
        //执行退款操作
        $ress = UserPayLogic::ref($this->uuid, FR_FINANCE_WECHAT, $thirdPayParam);
        Debug::debug('执行退款返回结果', $ress);
        if ($ress['return_code'] == 'SUCCESS' && $ress['result_code'] == 'SUCCESS') {
            return $ress;
        } else {
            // 20220917?????TODO 更优？？
            if ($ress['err_code_des'] == '订单已全额退款') {
                return $ress;
            }
            $errMsg = $ress['return_msg'] == 'OK' ? $ress['err_code_des'] : $ress['return_msg'];
            throw new Exception('退款渠道报错：' . $errMsg);
        }
    }

    /**
     * 2022-11-24:主动查单接口：
     * 一般用于删除之前
     * 【TODO】:抽象为多种查询方式
     */
    public function payQuery() {
        $info = $this->get();
        $class = '';
        if(Arrays::value($info, 'pay_by')){
            $class = self::payQueryClass($info['pay_by'], $info['change_type']);
            $res = $class::payQuery($this->uuid);
        } else {
            $res = WechatWxPayLogService::payQuery($this->uuid);
        }

        return $res;
    }
    /**
     * 20230904
     * 提取支付结果存储类库
     * @param type $payBy   支付渠道
     * @param type $changeType    1收款，2付款
     * @return string
     */
    protected static function payQueryClass($payBy,$changeType){
        $prefix = config('database.prefix');

        $r['cmbSkt_1'] = $prefix.'thirdpay_cmb_pay_log';
        $r['wechat_1'] = $prefix.'wechat_wx_pay_log';

        $key    = $payBy.'_'.$changeType;
        $table  = Arrays::value($r, $key, '');
        if(!$table){
            return '';
        }
        
        return DbOperate::getService($table);
    }
    
    /**
     * 20230904:
     * 标记支付渠道（调用支付参数时处理）
     */
    public function setPayByRam($payBy){
        $data = [];
        $data['pay_by'] = $payBy;
        $this->updateRam($data);
    }

    /**
     * 20230522：客户管理员视角
     * @param type $con
     */
    public static function paginateForCustomerManager($con) {
        // 只提取管理员
        $customerIds    = CustomerUserService::userManageCustomerIds(session(SESSION_USER_ID));
        $con[]          = ['customer_id', 'in', $customerIds];
        $lists = self::paginateRaw($con);
        return $lists;
    }
    /**
     * 20230807:计算账单金额
     */
    protected function calNeedPayPrize(){
        $lists = $this->objAttrsList('financeStatementOrder');
        // dump($lists);
        return Arrays2d::sum($lists, 'need_pay_prize');
    }
    /*
     * 20230807:改价
     */
    public function updatePrizeRam(){
        $info = $this->get();
        $needPayPrize = $this->calNeedPayPrize();
        if($info['need_pay_prize'] == $needPayPrize){
            //价格一样不用改
            return true;
        }
        if($info['has_settle']){
            throw new Exception('已结账单不可改价'.$this->uuid);
        }
        $data['need_pay_prize'] = $needPayPrize;
        return $this->doUpdateRamClearCache($data);
    }
}

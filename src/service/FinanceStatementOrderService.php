<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use xjryanse\goods\service\GoodsPrizeTplService;
use xjryanse\order\service\OrderService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\wechat\service\WechatWxPayLogService;
use think\Db;
use Exception;

/**
 * 收款单-订单关联
 */
class FinanceStatementOrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatementOrder';
    //直接执行后续触发动作
    protected static $directAfter = true;

    // 20230710：开启方法调用统计
    protected static $callStatics = true;
    
    // 分开比较好管理
    use \xjryanse\finance\service\statementOrder\PaginateTraits;
    use \xjryanse\finance\service\statementOrder\FieldTraits;
    use \xjryanse\finance\service\statementOrder\TriggerTraits;
    use \xjryanse\finance\service\statementOrder\CalTraits;
    use \xjryanse\finance\service\statementOrder\DoTraits;

    /**
     * 取消支付后删除对应的账单。
     * TODO建议调用一下查单接口
     * @return boolean
     * @throws Exception
     */
    public function payCancel() {
        $financeStatementOrder = $this->get();
        $statementId = Arrays::value($financeStatementOrder, 'statement_id');
        //调用取消订单接口
        //取消结算
        if ($statementId) {
            //获取账单信息
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();
            if ((time() - strtotime($statementInfo['create_time'])) < 2) {
                throw new Exception('账单操作频繁，请稍后再试');
            }
            //20220310
            if ($statementInfo['has_settle']) {
                throw new Exception('账单已结不可操作');
            }
            //单笔账单才处理，多笔不处理
            /* 20220310，为了兼容hx往返付款，进行调整，TODO需进行安全性测试
              $con[] = ['statement_id','=',$statementId];
              $orderCount = self::mainModel()->where($con)->count('distinct order_id');
              if($orderCount > 1){
              throw new Exception('账单'.$statementId.'非单笔支付账单，请联系客服');
              }
              if($statementInfo['order_id'] != $financeStatementOrder['order_id']){
              throw new Exception('订单号不匹配，请联系开发，账单'.$statementId);
              } */
            // 删除账单
            Db::startTrans();
            // 取消结算
            FinanceStatementService::getInstance($statementId)->update(['has_confirm' => 0]);
            FinanceStatementService::getInstance($statementId)->delete();
            Db::commit();
        }
        return true;
    }

    /**
     * 20230903:增加ram
     * 取消支付后删除对应的账单。
     * TODO建议调用一下查单接口
     * @return boolean
     * @throws Exception
     */
    public function payCancelRam() {
        $financeStatementOrder = $this->get();
        $statementId = Arrays::value($financeStatementOrder, 'statement_id');
        //调用取消订单接口
        //取消结算
        if ($statementId) {
            //获取账单信息
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();
            if ((time() - strtotime($statementInfo['create_time'])) < 2) {
                throw new Exception('账单操作频繁，请稍后再试');
            }
            //20220310
            if ($statementInfo['has_settle']) {
                throw new Exception('账单已结不可操作');
            }

            // 取消结算
            FinanceStatementService::getInstance($statementId)->updateRam(['has_confirm' => 0]);
            FinanceStatementService::getInstance($statementId)->deleteRam();
        }
        return true;
    }
    
    /**
     * 清除未处理的账单
     * 一般用于订单取消，撤销全部的订单
     * ！！【未测】20210402
     */
    public static function clearOrderNoDeal($orderId, $cate = '') {        
        Debug::debug(__CLASS__ . __FUNCTION__, $orderId);
        self::checkTransaction();
        if (!$orderId) {
            return false;
            // throw new Exception('订单id必须');
        }
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['has_statement', '=', 0];   //未出账单
        $con[] = ['has_settle', '=', 0];      //未结算
        // 20220615：账单类型
        if ($cate) {
            $con[] = ['statement_cate', '=', $cate];      //账单类型过滤
        }
        //$listFilter = self::mainModel()->where( $con )->select();
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        Debug::debug(__CLASS__ . __FUNCTION__ . '的$con', $con);
        Debug::debug(__CLASS__ . __FUNCTION__ . '的$listFilter', $listFilter);
        foreach ($listFilter as $k => $v) {
            self::getInstance($v['id'])->delete();
        }
    }

    /**
     * 20220619:逐步替代上方clearOrderNoDeal 方法
     * @param type $orderId
     * @param type $cate
     * @throws Exception
     */
    public static function clearOrderNoDealRam($orderId, $cate = '') {
        Debug::debug(__CLASS__ . __FUNCTION__, $orderId);
        if (!$orderId) {
            throw new Exception('订单id必须');
        }
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['has_statement', '=', 0];   //未出账单
        $con[] = ['has_settle', '=', 0];      //未结算
        // 20240121：增加条件：来源表判断，避免误杀包车趟次录入的价格数据
        $orderTable     = OrderService::getTable();
        $con[]          = ['belong_table', '=', $orderTable];      //未结算
        // 
        // 20220615：账单类型
        if ($cate) {
            $con[] = ['statement_cate', '=', $cate];      //账单类型过滤
        }
        //$listFilter = self::mainModel()->where( $con )->select();
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
//        dump($listFilter);
        Debug::debug(__CLASS__ . __FUNCTION__ . '的$con', $con);
        Debug::debug(__CLASS__ . __FUNCTION__ . '的$listFilter', $listFilter);
        foreach ($listFilter as $k => $v) {
            self::getInstance($v['id'])->deleteRam();
        }
    }

    
    /**
     * 
     * @param type $info    最新的信息（回传回调）：save和update 为最终，delete为删除前
     */
    protected static function dealFinanceCallBack($info){
        //策略模式
        $belongTable    = Arrays::value($info, 'belong_table');
        $belongTableId  = Arrays::value($info, 'belong_table_id');
        $service        = DbOperate::getService($belongTable);
        // 如果处理回调方法存在，则调用
        if ( $service && method_exists($service, 'dealFinanceCallBack') && $belongTableId) {
            $service::getInstance($belongTableId)->dealFinanceCallBack($info);
        }
    }

    /**
     * 前序关联保存
     * @param type $thisData        本次保存的数据
     * @param type $preOrderId      前序订单编号
     */
    public static function preUniSave($data) {
        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        //客户账单，才进行前向处理
        if ($statementCate != 'buyer') {
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_PRE) {
            return '';
        }

        $preOrderInfo = OrderService::getInstance($orderId)->getPreData('pre_order_id');
        //当前订单的销售类型
        $saleType = Arrays::value($preOrderInfo, 'order_type');
        $prizeKey = GoodsPrizeTplService::getPreKey($saleType, $data['statement_type']);
        $resData[DIRECTION] = DIRECT_PRE;
        $resInfo = self::prizeKeySaveRam($prizeKey, $preOrderInfo['id'], $data['need_pay_prize'], $resData);
        return $resInfo ? $resInfo['id'] : '';
    }

    /**
     * 后序关联保存
     * @param type $thisData        本次保存的数据
     * @param type $afterOrderId      后序订单编号
     */
    protected static function afterUniSave($data) {
        $orderId = Arrays::value($data, 'order_id');
        $statementCate = Arrays::value($data, 'statement_cate');
        //供应商账单，才进行后向处理
        if ($statementCate != 'seller') {
            return false;
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if ($dealDirection && $dealDirection != DIRECT_AFT) {
            return false;
        }
        $afterOrderArr = OrderService::getInstance($orderId)->getAfterDataArr('pre_order_id');
        foreach ($afterOrderArr as $afterOrderInfo) {
            $saleType = Arrays::value($afterOrderInfo, 'order_type');
            //关联后续订单收款
            $prizeKey = GoodsPrizeTplService::getAfterKey($saleType, $data['statement_type']);
            $resData = [];
            $resData['pre_statement_order_id'] = $data['id'];
            $resData[DIRECTION] = DIRECT_AFT;
            if ($prizeKey) {
                self::prizeKeySaveRam($prizeKey, $afterOrderInfo['id'], $data['need_pay_prize'], $resData);
            }
        }
        return true;
    }



    /**
     * 由外部直接添加一下退款单（一般用于买了两张退一张的情况）
     */
    public static function addRef($orderId, $refundPrize, $statementType = "directRef") {
        self::checkTransaction();
        $data['is_ref'] = 1;
        $data['order_id'] = $orderId;
        $data['need_pay_prize'] = -1 * abs($refundPrize);
        $data['statement_cate'] = 'buyer';
        $data['statement_type'] = $statementType;
        // 退款
        $res = self::save($data);
        return $res;
    }

    /**
     * 20220318
     * 传入一个订单号和订单总额，查询已有账单，进行多退少补
     * 一般用于订单改价，包车车辆运费调整
     * @param type $orderId
     * @param type $money
     */
    public static function updateOrderMoney($orderId, $money) {
        if (OrderService::getInstance($orderId)->fIsComplete()) {
            throw new Exception('订单已结不可操作');
        }
        //20220615增加；
        OrderService::mainModel()->where('id', $orderId)->update(['order_prize' => $money]);
        $con[] = ['order_id', '=', $orderId];
        $statementOrders = self::mainModel()->where($con)->select();
        $statementOrdersArr = $statementOrders ? $statementOrders->toArray() : [];

        $con1[] = ['has_statement', '=', 1];
        // 20220608，开发向供应商付款的逻辑。增加buyer条件
        $con1[] = ['statement_cate', '=', 'buyer'];
        $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr, $con1), 'need_pay_prize'));

        //剩余应付金额
        $remainNeedPayMoney = $money - $hasSettleMoney;
        self::clearOrderNoDeal($orderId);
        if ($remainNeedPayMoney > 0) {
            //如为应收；
            $prizeKey = "GoodsPrize";
            // 应收
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        } else if ($remainNeedPayMoney < 0) {
            // 应退
            $prizeKey = "normalRef";
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        }

        return $remainNeedPayMoney;
    }

    /**
     * 20220619
     * @param type $orderId
     * @param type $money
     * @return type
     * @throws Exception
     */
    public static function updateOrderMoneyRam($orderId, $money) {
        if (OrderService::getInstance($orderId)->fIsComplete()) {
            throw new Exception('订单已结不可操作');
        }
        $statementCate = 'buyer';
        self::clearOrderNoDealRam($orderId, $statementCate);
        //20220615增加；
        OrderService::getInstance($orderId)->setUuData(['order_prize' => $money]);
        $statementOrdersArr = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        // 20240121:改取全部的账单明细，这样才总金额才不会超
        // $con1[] = ['has_statement', '=', 1];
        // 20220608，开发向供应商付款的逻辑。增加buyer条件
        $con1[] = ['statement_cate', '=', $statementCate];
        $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr, $con1), 'need_pay_prize'));

        //20220624:增加在途
        //$onRoadMoney = self::onRoadSavePrize($orderId, $statementCate);
        //剩余应付金额
        $remainNeedPayMoney = $money - $hasSettleMoney; // - $onRoadMoney;
        // 20231030：增加一些关联的字段
        $saveData                   = [];
        $saveData['original_prize'] = $remainNeedPayMoney;
        $saveData['discount_prize'] = 0;
        // $saveData['is_needpay']     = 1;
        // dump('$statementOrdersArr');
        // dump($money);
        // dump($statementOrdersArr);
        // dump($remainNeedPayMoney);
        if ($remainNeedPayMoney > 0) {
            //如为应收；
            $prizeKey = "GoodsPrize";
            // 应收
            self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney, $saveData);
        } else if ($remainNeedPayMoney < 0) {
            // 应退
            $prizeKey = "normalRef";
            self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney, $saveData);
        }

        return $remainNeedPayMoney;
    }

    /**
     * 20220622：更新订单的应付金额
     * @param type $orderId

     * @param type $moneyArr    
      ['供应商1'=>100,'供应商2'=>200]
      ['customer_id'=>1,'user_id'=>'2','need_pay_prize'=>'3']
     * * @return type
     * @throws Exception
     */
    public static function updateNeedOutcomePrizeRam($orderId, $moneyArr) {
        if (OrderService::getInstance($orderId)->fIsComplete()) {
            throw new Exception('订单已结不可操作');
        }
        //20220615增加；
        //OrderService::getInstance($orderId)->setUuData(['need_outcome_prize'=>$money]);        
        $statementCate = 'seller';
        self::clearOrderNoDealRam($orderId, $statementCate);
        $statementOrdersArr = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        //20220622
        //foreach($moneyArr as $customerId=>$money){
        foreach ($moneyArr as $mData) {
            $con1 = [];
            $con1[] = ['customer_id', '=', Arrays::value($mData, 'customer_id')];
            $con1[] = ['user_id', '=', Arrays::value($mData, 'user_id')];
            $con1[] = ['has_statement', '=', 1];
            // 20220608，开发向供应商付款的逻辑。增加seller条件
            $con1[] = ['statement_cate', '=', $statementCate];
            $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr, $con1), 'need_pay_prize'));
            //20220624:增加在途
            //$onRoadMoney            = self::onRoadSavePrize($orderId, $statementCate, Arrays::value($mData, 'customer_id'), Arrays::value($mData, 'user_id'));
            //剩余应付金额
            $remainNeedPayMoney = $mData['need_pay_prize'] - $hasSettleMoney; // - $onRoadMoney;
//            dump('11111111111');
//            dump($mData['need_pay_prize']);
//            dump($hasSettleMoney);
//            dump($onRoadMoney);
//            dump($remainNeedPayMoney);

            $savData['customer_id'] = Arrays::value($mData, 'customer_id');
            $savData['user_id'] = Arrays::value($mData, 'user_id');
            if ($remainNeedPayMoney < 0) {
                //如为应付款；
                $prizeKey = "sellerGoodsPrize";
                self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney, $savData);
            } else if ($remainNeedPayMoney > 0) {
                // 付款的退款：
                $prizeKey = "sellerNormalRef";
                self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney, $savData);
            }
        }
    }

    /**
     * 20220624：获取在途未保存的价格
     * @global type $glSaveData
     * @param type $orderId
     * @param type $statementCate
     * @param type $customerId      供应商
     * @param type $userId          客户
     * @return type
     */
    public static function onRoadSavePrize($orderId, $statementCate, $customerId = '', $userId = '') {
        global $glSaveData;
        $tableName = self::getTable();
        $listsAll = Arrays::value($glSaveData, $tableName, []);
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['has_settle', '=', 0];
        $con[] = ['statement_cate', '=', $statementCate];
        //付款的可以多个供应商
        if ($customerId) {
            $con[] = ['customer_id', '=', $customerId];
        }
        if ($userId) {
            $con[] = ['user_id', '=', $userId];
        }
        return array_sum(array_column(Arrays2d::listFilter($listsAll, $con), 'need_pay_prize'));
    }

    /**
     * 20220615：更新订单的应付金额
     */
    public static function updateNeedOutcomePrize($orderId, $money) {
        if (OrderService::getInstance($orderId)->fIsComplete()) {
            throw new Exception('订单已结不可操作');
        }
        //20220615增加；
        OrderService::mainModel()->where('id', $orderId)->update(['need_outcome_prize' => $money]);
        $con[] = ['order_id', '=', $orderId];
        $statementOrders = self::mainModel()->where($con)->select();
        $statementOrdersArr = $statementOrders ? $statementOrders->toArray() : [];

        $con1[] = ['has_statement', '=', 1];
        // 20220608，开发向供应商付款的逻辑。增加buyer条件
        $con1[] = ['statement_cate', '=', 'seller'];
        $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr, $con1), 'need_pay_prize'));
        //剩余应付金额
        $remainNeedPayMoney = $money - $hasSettleMoney;

        self::clearOrderNoDeal($orderId, 'seller');
        if ($remainNeedPayMoney > 0) {
            //如为应付；
            $prizeKey = "sellerGoodsPrize";
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        } else if ($remainNeedPayMoney < 0) {
            // 应退
            $prizeKey = "sellerNormalRef";
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        }
        return $remainNeedPayMoney;
    }


    /**
     * 账单id，触发订单流程
     * 一般用于账单结账后触发订单
     */
    public static function statementIdTriggerOrderFlow($statementId) {
        self::checkTransaction();
//        $con[] = ['statement_id','=',$statementId];
//        $orderIds = self::mainModel()->where($con)->column('distinct order_id');
        $lists = FinanceStatementService::getInstance($statementId)->objAttrsList('financeStatementOrder');
        $orderIds = array_unique(array_column($lists, 'order_id'));
        Debug::debug('FinanceStatementOrderService触发关联订单动作', $orderIds);
        //触发动作
        foreach ($orderIds as $orderId) {
            // 20230805:增加判断：为了兼容没有orderId的情况
            if($orderId){
                OrderFlowNodeService::lastNodeFinishAndNext($orderId);
            }
        }
    }

    /**
     * 对账单商品id
     */
    public static function statementGoodsName($statementId) {
        $con[] = ['statement_id', '=', $statementId];
        return self::conGoodsName($con);
    }

    /**
     * 对账单商品id
     */
    public static function statementOrderGoodsName($ids) {
        foreach($ids as $k=>$id){
            if(!$id){
                unset($ids[$k]);
            }
        }
        if(!$ids){
            return '';
        }
        $con[] = ['id', 'in', $ids];
        return self::conGoodsName($con);
    }

    /**
     * 条件取商品名
     * @param type $con
     * @return type
     */
    protected static function conGoodsName($con = []) {
        $idSql = FinanceStatementOrderService::mainModel()->where($con)->field('distinct order_id')->buildSql();
        $orderTable = OrderService::getTable();
        $sql = '( SELECT `goods_name` FROM `' . $orderTable . '` WHERE  `id` in ' . $idSql . ') ';
        $res = Db::query($sql);
        return implode(',', array_column($res, 'goods_name'));
    }

    

    /**
     * 2022-12-22:账单订单写入
     * @param type $orderId
     * @param type $data
     */
    public static function orderAttrFinanceStatementOrderPush($orderId, $data) {
        $orderAttrList = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $dataId = Arrays::value($data, 'id');
        $con[] = ['id', '=', $dataId];
        if (!Arrays2d::listFind($orderAttrList, $con)) {
            OrderService::getInstance($orderId)->objAttrsPush('financeStatementOrder', $data);
        }
        return true;
    }

    /**
     * 价格key保存
     * @param type $prizeKey    价格key
     * @param type $orderId     订单id
     * @param type $prize       价格
     */
    public static function prizeKeySave($prizeKey, $orderId, $prize, $data = []) {
        //判断订单有key不执行
        Debug::debug('addFinanceStatementOrder的$prizeKey', $prizeKey);
        // 有价格才写入
        if (!$prize) {
            return false;
        }
        $goodsPrizeInfo = GoodsPrizeKeyService::getByPrizeKey($prizeKey);  //价格key取归属
        //key不可重复添加时，判断有key不执行
        if (Arrays::value($goodsPrizeInfo, 'is_duplicate')) {
            if (self::hasStatementOrder($orderId, $prizeKey)) {
                return false;
            }
        }

        $prizeKeyRole = GoodsPrizeKeyService::keyBelongRole($prizeKey);
        $data['order_id'] = $orderId;
        $data['change_type'] = Arrays::value($goodsPrizeInfo, 'change_type');
        $data['statement_cate'] = $prizeKeyRole;  //价格key取归属
        $data['need_pay_prize'] = $data['change_type'] == 1 ? abs($prize) : -1 * abs($prize);
        $data['statement_type'] = $prizeKey;
        //增加是否退款的判断
        $data['is_ref'] = Arrays::value($goodsPrizeInfo, 'type') == 'ref' ? 1 : 0;
        Debug::debug('【最终添加】addFinanceStatementOrder，的data', $data);
        return self::save($data);
    }

    /**
     * 20220619 价格key保存
     * @param type $prizeKey    价格key
     * @param type $orderId     订单id
     * @param type $prize       价格
     */
    public static function prizeKeySaveRam($prizeKey, $orderId, $prize, $data = []) {
        $dataSave = self::prizeKeyArrForSave($prizeKey, $orderId, $prize, $data);
        if (!$dataSave) {
            return false;
        }
        return self::saveRam($dataSave);
    }

    /**
     * 20240419：准备替代上述方法
     * @param type $prizeKey
     * @param type $prize
     * @param type $data
     * @return type
     */
    public static function prizeGetIdForSettle($prizeKey, $prize, $data = []){
        $keys   = ['order_id','sub_id','belong_table','belong_table_id','is_un_prize'];
        $dataS  = Arrays::getByKeys($data, $keys);
        $dataS['statement_type']    = $prizeKey;
        // 未交款
        $dataS['has_confirm']       = 0;
        // 未结算
        $dataS['has_settle']        = 0;
        $id                         = self::commGetIdEG($dataS);
        // Debug::dump($id);
        //应付金额拿来更新
        $dataUpd['need_pay_prize']  = $prize;
        self::getInstance($id)->updateRam($dataUpd);

        return self::getInstance($id)->get();
    }

    /**
     * 20230726
     * 跳过订单逻辑，直接用来源表写入订单，更加灵活
     * @param type $prizeKey
     * @param type $prize
     * @param type $belongTable
     * @param type $belongTableId
     * @param type $data
     */
    public static function belongTablePrizeKeySaveRam($prizeKey,$prize,$belongTable,$belongTableId,$data = []){
        // 有价格才写入
        if (!$prize || !$prizeKey) {
            // 20240523:发现有部分难以清点的金额，客户会以0入账
            // return false;
        }
        $data['statement_type'] = $prizeKey;
        $data['need_pay_prize'] = $prize;
        $data['belong_table']   = $belongTable;
        $data['belong_table_id']   = $belongTableId;
        $goodsPrizeInfo = GoodsPrizeKeyService::getByPrizeKey($prizeKey);  //价格key取归属
        //key不可重复添加时，判断有key不执行
        if (Arrays::value($goodsPrizeInfo, 'is_duplicate')) {
            $subId = Arrays::value($data, 'sub_id');
            if (self::belongTableHasStatementOrder($belongTable,$belongTableId, $prizeKey, $subId)) {
                return false;
            }
        }
        $prizeKeyRole = GoodsPrizeKeyService::keyBelongRole($prizeKey);
        // 20230726:没有订单号属性
        // $data['order_id']       = '';
        $data['change_type']    = Arrays::value($goodsPrizeInfo, 'change_type');
        $data['statement_cate'] = $prizeKeyRole;  //价格key取归属
        $data['need_pay_prize'] = $data['change_type'] == 1 ? abs($prize) : -1 * abs($prize);
        $data['statement_type'] = $prizeKey;
        //增加是否退款的判断
        $data['is_ref'] = Arrays::value($goodsPrizeInfo, 'type') == 'ref' ? 1 : 0;

        return self::saveRam($data);
    }
    
    /**
     * 20220803：适用于保存价格key的
     * @param type $prizeKey
     * @param type $orderId
     * @param type $prize
     * @param type $data
     * @return boolean
     */
    public static function prizeKeyArrForSave($prizeKey, $orderId, $prize, $data = []) {
        // 有价格才写入
        if (!$prize || !$prizeKey) {
            return false;
        }
        $goodsPrizeInfo = GoodsPrizeKeyService::getByPrizeKey($prizeKey);  //价格key取归属
        //key不可重复添加时，判断有key不执行
        if (Arrays::value($goodsPrizeInfo, 'is_duplicate')) {
            $subId = Arrays::value($data, 'sub_id');
            if (self::hasStatementOrder($orderId, $prizeKey, $subId)) {
                return false;
            }
        }
        $prizeKeyRole = GoodsPrizeKeyService::keyBelongRole($prizeKey);
        $data['order_id'] = $orderId;
        $data['change_type'] = Arrays::value($goodsPrizeInfo, 'change_type');
        $data['statement_cate'] = $prizeKeyRole;  //价格key取归属
        $data['need_pay_prize'] = $data['change_type'] == 1 ? abs($prize) : -1 * abs($prize);
        $data['statement_type'] = $prizeKey;
        //增加是否退款的判断
        $data['is_ref'] = Arrays::value($goodsPrizeInfo, 'type') == 'ref' ? 1 : 0;
        Debug::debug('【最终添加】addFinanceStatementOrder，的data', $data);

        return $data;
    }

    /**
     * 更新退款状态
     */
    public function setHasRef() {
        $con[] = ['ref_statement_order_id', '=', $this->uuid];
        $count = FinanceStatementOrderService::count($con);
        $hasRef = $count ? 1 : 0;
        $this->update(['has_ref' => $hasRef]);
    }

    /**
     * 根据订单id和价格key，查已结算价格
     */
    public static function hasSettleMoney($orderId, $prizeKeys) {
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['statement_type', 'in', $prizeKeys];
        $con[] = ['has_settle', '=', 1];

        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        return array_sum(array_column($listFilter, 'need_pay_prize'));
    }

    /**
     * 价格账单是否已存在
     * @param type $orderId
     * @param type $prizeKeys
     * @return type
     */
    public static function hasStatementOrder($orderId, $prizeKeys, $subId = '') {
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['statement_type', '=', $prizeKeys];
        //20220803:增加子id
        if ($subId) {
            $con[] = ['sub_id', '=', $subId];
        }

        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        return count($listFilter);
        //return self::count($con);
    }
    /**
     * 20230815：来源表取账单明细id
     * @param type $belongTable
     * @param type $belongTableId
     * @param type $con
     */
    public static function belongTableStatementOrderIds($belongTable, $belongTableId, $con = []){
        $con[] = ['belong_table', '=', $belongTable];
        $con[] = ['belong_table_id', 'in', $belongTableId];
        return self::ids($con);
    }
    /**
     * 源表提取明细
     * @param type $belongTable
     * @param type $belongTableId
     * @param type $con
     * @return type
     */
    public static function belongTableStatementOrders($belongTable, $belongTableId, $con = []){
        $con[] = ['belong_table', '=', $belongTable];
        $con[] = ['belong_table_id', 'in', $belongTableId];
        return self::where($con)->select();
    }
    /**
     * 源表的已结算金额
     * @param type $belongTable
     * @param type $belongTableId
     * @param type $con
     * @return type
     */
    public static function belongTableSettleMoney($belongTable, $belongTableId, $con = []){
        $con[] = ['belong_table', '=', $belongTable];
        $con[] = ['belong_table_id', 'in', $belongTableId];
        $con[] = ['has_settle', 'in', 1];
        return self::where($con)->sum('need_pay_prize');
    }
    
    //20231231:报销跨表
    public static function belongTableIdStatementOrderIds($belongTableId, $con = []){
        $con[] = ['belong_table_id', 'in', $belongTableId];
        return self::ids($con);
    }
    
    /**
     * 20230726: 价格账单是否已存在
     * @param type $orderId
     * @param type $prizeKeys
     * @return type
     */
    public static function belongTableHasStatementOrder($belongTable, $belongTableId, $prizeKeys, $subId = '') {
        // 20231228:增加去除内存已删未提交的id
        $thisTable  = self::getTable();
        $deletedIds = DbOperate::tableGlobalDeleteIds($thisTable);
        if($deletedIds){
            $con[] = ['id','not in',$deletedIds];
        }

        $con[] = ['statement_type', '=', $prizeKeys];
        //20220803:增加子id
        if ($subId) {
            $con[] = ['sub_id', '=', $subId];
        }
        $ids = self::belongTableStatementOrderIds($belongTable, $belongTableId, $con);

        return count($ids);
    }
   

    public static function extraDetail(&$item, $uuid) {
        if (!$item) {
            return false;
        }
        $orderId = Arrays::value($item, "order_id");
        $item['fGoodsName'] = OrderService::getInstance($orderId)->fGoodsName();
        return $item;
    }

    /**
     * 2022-11-12
     * @param type $ids
     * @return type
     */
    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {
                    $statementLists = FinanceStatementService::groupBatchFind(array_column($lists, 'statement_id'));
                    $accountLogIds = array_column($statementLists, 'account_log_id');
                    $accountLogInfos = FinanceAccountLogService::groupBatchFind($accountLogIds);

                    $accountIds = array_column($accountLogInfos, 'account_id');
                    $accountInfos = FinanceAccountService::groupBatchFind($accountIds);

                    foreach ($lists as &$v) {
                        $stId = $v['statement_id'];
                        // 2022-11-12收款记录id
                        $v['accountLogId']  = isset($statementLists[$stId]) ? $statementLists[$stId]['account_log_id'] : '';
                        $v['accountId']     = $accountLogInfos && isset($accountLogInfos[$v['accountLogId']]) 
                                ? $accountLogInfos[$v['accountLogId']]['account_id'] 
                                : '';
                        $v['accountName']   = $accountInfos && isset($accountInfos[$v['accountId']]) 
                                ? $accountInfos[$v['accountId']]['account_name'] 
                                : '';
                        // 收款时间
                        $v['accountBillTime'] = isset($statementLists[$stId]) ? $statementLists[$stId]['account_bill_time'] : '';
                    }
                    return $lists;
                }, true);
    }

    /**
     * 20220615 获取应收客户的金额
     * 0615:已结定数
     */
    public static function getBuyerPrize($orderId, $onlySettle = true) {
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $con[] = ['order_id', 'in', $orderId];
        $con[] = ['statement_cate', '=', 'buyer'];
        if ($onlySettle) {
            $con[] = ['has_settle', '=', 1];
        }
        return array_sum(array_column(Arrays2d::listFilter($listsAll, $con), 'need_pay_prize'));
    }

    /**
     * 20220615 获取应付供应商的金额
     * 0615:已结定数
     */
    public static function getSellerPrize($orderId, $onlySettle = true) {
        $con[] = ['order_id', 'in', $orderId];
        $con[] = ['statement_cate', '=', 'seller'];
        if ($onlySettle) {
            $con[] = ['has_settle', '=', 1];
        }
        return self::sum($con, 'need_pay_prize');
    }

    /**
     * 重新校验未结算账单的金额
     * （进行修改）
     */
    public static function reCheckNoSettle($orderId) {
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['has_settle', '=', 0];
        $lists = self::lists($con);
        foreach ($lists as $value) {
            Debug::debug('reCheckNoSettle的循环value', $value);
            $prize = OrderService::getInstance($orderId)->prizeKeyGetPrize($value['statement_type']);
            Debug::debug('reCheckNoSettle的循环$prize', $prize);
            //更新未结账单金额
            if ($prize) {
                self::getInstance($value['id'])->update(['need_pay_prize' => $prize]);
                Debug::debug('reCheckNoSettle的循环$prize', $prize);
            } else if ($value['is_ref'] == 0) {
                //20210511 线上退款bug，增加退款不删
                //【没有价格】：直接把账单删了；加个锁
                // 20210424 测试到手工录入的价格bug，增加“未出账单”条件
                $delCon = [];
                $delCon[] = ['id', '=', $value['id']];
                $delCon[] = ['has_statement', '=', 0];    //未出账单
                $delCon[] = ['has_settle', '=', 0];
                // TODO 延长定金有bug，暂时隐藏20210520，再考虑收公证定金的情况【】
//                self::mainModel()->where( $delCon )->delete();
            }
        }
    }

    /**
     * 根据订单查询应付账单
     */
    public static function needPayStatementOrderIds($orderId, $changeType = 1, $statementCate = 'buyer') {
        $con[] = ['order_id', 'in', $orderId];
        $con[] = ['change_type', '=', $changeType];
        $con[] = ['statement_cate', '=', $statementCate];
        $con[] = ['has_settle', '=', 0];
        return self::mainModel()->where($con)->column('id');
    }

    /**
     * 性能问题，逐步淘汰
     * 获取账单id，无记录时生成账单
     * @param type $reGenerate  已有未结账单是否重新生成
     * @return type
     * @throws Exception
     */
    public static function getStatementIdWithGenerate($statementOrderIds, $reGenerate = false) {
        if (!is_array($statementOrderIds)) {
            $statementOrderIds = [$statementOrderIds];
        }
        
        // 20230903：移到外面试试，避免一直循环
        WechatWxPayLogService::dealBatch();
        $con[] = ['id', 'in', $statementOrderIds];
        $statementOrderInfos = self::listSetUudata($con, MASTER_DATA);
        Debug::debug('getStatementIdWithGenerate调试打印', $statementOrderInfos);
        //20220301:微信支付尝试批量处理,批量请求有bug20220302

        $statementIds = [];
        // 多个账单循环取消
        foreach ($statementOrderIds as $statementOrderId) {
            //有账单直接取账单号
            $info = self::getInstance($statementOrderId)->get(MASTER_DATA);
            if (!$info) {
                throw new Exception('账单' . $statementOrderId . '不存在');
            }
            if($info['has_settle']){
                throw new Exception('账单' . $statementOrderId . '已结算');
            }
            $statementIds[$statementOrderId] = $info['statement_id'];
            // 有账单；未结；重新生成
            if ($info['statement_id'] && !$info['has_settle'] && $reGenerate) {
                //时间判断
                $time = session('lastPayWxTime') ?: 0;
                $timeNew = time();
                if ($timeNew - $time <= 10) {
                    throw new Exception('操作频繁，请稍后再试' . ($timeNew - $time));
                }

                // 取消账单
                // 20220302暂时注释
                // Db::startTrans();
                self::getInstance($statementOrderId)->payCancel();
                // Db::commit();
                $statementIds[$statementOrderId] = '';
            }
        }

        //明细对应多个账单
        if (count(array_unique($statementIds)) > 1) {
            throw new Exception('账单明细对应了多个账单，部分已结无法取消，请联系开发');
        }

        $uniqIds = array_unique($statementIds);
        $statementId = $uniqIds ? array_pop($uniqIds) : '';
        if (!$statementId) {
            //重新生成账单
            $financeStatement = FinanceStatementService::statementGenerate($statementOrderIds);
            $statementId = $financeStatement['id'];
        }
        return $statementId;
    }
    
    /**
     * 获取账单id，无记录时生成账单
     * @param type $reGenerate  已有未结账单是否重新生成
     * @return type
     * @throws Exception
     */
    public static function getStatementIdWithGenerateRam($statementOrderIds, $reGenerate = false, $data = []) {
        if (!is_array($statementOrderIds)) {
            $statementOrderIds = [$statementOrderIds];
        }
        
        // 【1】处理微信支付账单，避免清理到已付款未结算的账单。
        WechatWxPayLogService::dealBatch();
        // 原来的已有账单，有直接返回
        $statementIdRaw = self::getStatementIdRaw($statementOrderIds);
        if($statementIdRaw){
            return $statementIdRaw;
        }
        
        // 【2】账单取消支付
        $con[] = ['id', 'in', $statementOrderIds];
        $statementOrderInfos = self::listSetUudata($con, MASTER_DATA);
        Debug::debug('getStatementIdWithGenerate调试打印', $statementOrderInfos);
        //20220301:微信支付尝试批量处理,批量请求有bug20220302
        $statementIds = [];
        // 多个账单循环取消
        foreach ($statementOrderIds as $statementOrderId) {
            //有账单直接取账单号
            $info = self::getInstance($statementOrderId)->get(MASTER_DATA);
            if (!$info) {
                throw new Exception('账单' . $statementOrderId . '不存在');
            }
            if($info['has_settle']){
                throw new Exception('账单' . $statementOrderId . '已结算');
            }
            $statementIds[$statementOrderId] = $info['statement_id'];
            // 有账单；未结；重新生成
            if ($info['statement_id'] && !$info['has_settle'] && $reGenerate) {
                //时间判断
                $time = session('lastPayWxTime') ?: 0;
                $timeNew = time();
                if ($timeNew - $time <= 10) {
                    throw new Exception('操作频繁，请稍后再试' . ($timeNew - $time));
                }

                // 取消账单
                self::getInstance($statementOrderId)->payCancelRam();

                $statementIds[$statementOrderId] = '';
            }
        }

        //明细对应多个账单
        if (count(array_unique($statementIds)) > 1) {
            throw new Exception('账单明细对应了多个账单，部分已结无法取消，请联系开发');
        }

        $uniqIds = array_unique($statementIds);
        $statementId = $uniqIds ? array_pop($uniqIds) : '';
        if (!$statementId) {
            //重新生成账单
            $financeStatement   = FinanceStatementService::statementGenerateRam($statementOrderIds, $data);
            $statementId        = $financeStatement['id'];
        }

        return $statementId;
    }

    /**
     * 空账单设定对账单id
     */
    public function setStatementId($statementId) {
        $info = $this->get(0);
        if (Arrays::value($info, 'statement_id')) {
            throw new Exception($this->uuid . '已经对应了对账单' . Arrays::value($info, 'statement_id'));
        }
        return $this->update(['statement_id' => $statementId, "has_statement" => 1]);
    }

    /**
     * 20220620
     */
    public function setStatementIdRam($statementId) {
        $info = $this->get(0);
        if (Arrays::value($info, 'statement_id')) {
            throw new Exception($this->uuid . '已经对应了对账单' . Arrays::value($info, 'statement_id'));
        }
        // 20240708:增加has_confirm
        return $this->updateRam(['statement_id' => $statementId, "has_statement" => 1,'has_confirm'=>1]);
    }

    /**
     * 取消对账单id
     * @param type $statementId
     * @return type
     * @throws Exception
     */
    public function cancelStatementId() {
        $info = $this->get(0);
        if (Arrays::value($info, 'has_settle')) {
            throw new Exception($this->uuid . '已经结算过了');
        }
        if (Arrays::value($info, 'has_confirm')) {
            // throw new Exception($this->uuid . '客户已经确认过了');
        }
        //20230918:系统处理中判断：
        FinanceStatementService::getInstance($info['statement_id'])->payingCheck();

        return $this->update(['statement_id' => "", "has_statement" => 0]);
    }

    public function cancelStatementIdRam() {
        $info = $this->get(0);
        if (Arrays::value($info, 'has_settle')) {
            throw new Exception($this->uuid . '已经结算过了');
        }
        if (Arrays::value($info, 'has_confirm')) {
            // throw new Exception($this->uuid . '客户已经确认过了');
        }
        return $this->updateRam(['statement_id' => "", "has_statement" => 0]);
    }

    /**
     * 订单表的对账字段
     */
    protected static function getOrderStatementField($statementCate) {
        return 'has_' . ($statementCate ? $statementCate . '_' : '') . 'statement';
    }

    /**
     * 20220620:基于内存的订单金额数据
     * @param type $orderId
     * @return type
     */
    public static function orderMoneyData($orderId) {
        //收款
        $lists = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        // dump($lists);
        $conPay[] = ['statement_cate', '=', 'buyer'];
        $conPay[] = ['change_type', '=', '1'];
        $conPay[] = ['has_settle', '=', '1'];
        $dataArr['pay_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conPay), 'need_pay_prize'));
        //付款
        $conOutcome[] = ['statement_cate', '=', 'seller'];
        $conOutcome[] = ['change_type', '=', '2'];
        $conOutcome[] = ['has_settle', '=', '1'];
        $dataArr['outcome_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conOutcome), 'need_pay_prize'));
        //收退
        $conRef[] = ['statement_cate', '=', 'buyer'];
        $conRef[] = ['change_type', '=', '2'];
        $conRef[] = ['has_settle', '=', '1'];
        $dataArr['refund_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conRef), 'need_pay_prize'));
        //付退
        $conOutcomeRef[] = ['statement_cate', '=', 'seller'];
        $conOutcomeRef[] = ['change_type', '=', '1'];
        $conOutcomeRef[] = ['has_settle', '=', '1'];
        $dataArr['outcome_refund_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conOutcomeRef), 'need_pay_prize'));
        //支出
        $conCost[] = ['statement_cate', '=', 'cost'];
        $conCost[] = ['has_settle', '=', '1'];
        $dataArr['cost_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conCost), 'need_pay_prize'));
        //毛利
        $conFinal[] = ['has_settle', '=', '1'];
        $dataArr['final_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conFinal), 'need_pay_prize'));

        return $dataArr;
    }
    
    /**
     * 20230807:改价格
     * 提供收款key；退款key；
     * @param type $belongTable
     * @param type $belongTableId
     * @param type $prize
     */
    public static function changePrizeRam($belongTable,$belongTableId, $prize, $prizeKey = 'GoodsPrize', $data = []){
        $con[] = ['belong_table','=',$belongTable];
        $con[] = ['belong_table_id','=',$belongTableId];

        $lists = self::where($con)->order('has_settle desc')->select();   
        //总价
        //已结算
        $hasSettleMoney = 0;
        //应收
        //应退
        //价格已结算，
        foreach($lists as $v){
            if($v['has_settle']){
                $hasSettleMoney += $v['need_pay_prize'];
                // 剩余差价
                $prize  = $prize - $hasSettleMoney;
                continue;
            }
            if($prize == 0 || $v['need_pay_prize'] * $prize < 0){
                // 待写入价格为0，或者待写入价格与应结价格符号不同，删
                self::getInstance($v['id'])->deleteRam();
            } else if(!$v['has_settle']){
                // 同正或同负
                if(abs($v['need_pay_prize'] + $prize) == abs($v['need_pay_prize']) + abs($prize)){
                    $sData                   = [];
                    $sData['need_pay_prize'] = $prize;
                    if(isset($data['original_prize'])){
                        $sData['original_prize'] = $data['original_prize'];
                        $sData['discount_prize'] = Arrays::value($data, 'discount_prize', 0);
                    }

                    self::getInstance($v['id'])->updateRam($sData);
                    // 剩余价格
                    $prize = 0;
                }
            }
        }
        // 20240523：有0金额入账的情况，金额难以清点
        if($prize > 0){
            Debug::dump('测试0金额入账');
            self::belongTablePrizeKeySaveRam($prizeKey, $prize, $belongTable, $belongTableId, $data);
        }
        if($prize < 0){
            $prizeKeyRef = 'normalRef';
            self::belongTablePrizeKeySaveRam($prizeKeyRef, $prize, $belongTable, $belongTableId, $data);
        }
        return true;
    }
    
    /*
     * 20230814 逐步淘汰
     * 20230903:测试项目在线支付时，发现性能不佳
     * 获取批量账单id，用于合并支付
     */
    public static function statementGenerate($ids){
        $statementId        = self::getStatementIdWithGenerate($ids, true);
        $financeStatement   = FinanceStatementService::getInstance( $statementId )->info();
        return $financeStatement;
    }
    
    /**
     * 20230903
     * 重新架构
     */
    public static function statementGenerateRam($ids, $data = []){
        // 获取账单id，无记录时生成账单
        $statementId        = self::getStatementIdWithGenerateRam($ids, true, $data);
        $financeStatement   = FinanceStatementService::getInstance( $statementId )->info();
        return $financeStatement;
    }
    /**
     * 设定该笔账单达成了结算条件
     */
    public function setNeedPayRam(){
        $data['is_needpay'] = 1;
        $this->doUpdateRam($data);
    }
    /**
     * 设定该笔账单未达成结算条件
     */
    public function setNoNeedPayRam(){
        $data['is_needpay'] = 0;
        $this->doUpdateRam($data);
    }
    
    
    /**
     * 20230905：原路退款返回
     */
    public function doRefRevert(){
        // 待退款账单明细
        $info               = $this->get();
        $statementId = Arrays::value($info, 'statement_id');
        // 提取账单编号
        if(!$statementId){
            throw new Exception('该明细未生成账单:'.$this->uuid);
        }
        // 执行账单逻辑的退款动作
        return FinanceStatementService::getInstance($statementId)->doRefRevert();
    }
    /**
     * 获取原有的账单(明细一样不重新生成账单)
     * @return string
     */
    public static function getStatementIdRaw($statementOrderIds){
        $con    = [];
        $con[]  = ['id','in',$statementOrderIds];

        $statementIds = self::where($con)->column('distinct statement_id');
        if(count($statementIds) != 1 || !$statementIds[0] ){
            return '';
        }
        
        $cone    = [];
        $cone[]  = ['statement_id','in',$statementIds];

        $stCount = self::where($con)->count();
        if($stCount != count($statementOrderIds)){
            return '';
        }
        
        return $statementIds[0];
    }
    /**
     * 20231123
     * 查询subId是否有已结算记录，一般用于删除校验
     * @param type $subId
     */
    public static function subIdHasSettleRecord($subId){
        $con    = [];
        $con[]  = ['sub_id','=',$subId];
        $con[]  = ['has_settle','=',1];
        return self::where($con)->count();
    }
    /**
     * 20240117：手续费分配
     */
    public static function chargeDistribute($charge,$statementIds, $data=[]){
        $arr = [];
        foreach($statementIds as $stId){
            $tArr = FinanceStatementService::getInstance($stId)->objAttrsList('financeStatementOrder');
            $arr = array_merge($arr, $tArr);
        }
        
        $sum = Arrays2d::sum($arr, 'need_pay_prize');
        // 费率
        $rate = $sum && $charge ? $charge / $sum : 0;
        if(!$rate){
            return false;
        }
        $allFee = 0;
        foreach($arr as $k=>&$v){
            
            if($k + 1 < count($arr)){
                $tmpCharge = intval($rate * $v['need_pay_prize'] * 100) / 100;
                $allFee += $tmpCharge;
            } else {
                // 最后一个用分摊，解决尾数差异问题
                $tmpCharge = $charge - $allFee;
            }

            self::getInstance($v['id'])->doUpdateRam(['charge'=>$tmpCharge]);
        }


    }
    
}

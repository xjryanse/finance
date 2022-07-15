<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use xjryanse\goods\service\GoodsPrizeTplService;
use xjryanse\order\service\OrderService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\wechat\service\WechatWxPayLogService;  
use xjryanse\finance\logic\PackLogic;
use think\Db;
use Exception;
/**
 * 收款单-订单关联
 */
class FinanceStatementOrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatementOrder';
    //直接执行后续触发动作
    protected static $directAfter = true;
    /**
     * 取消支付后删除对应的账单。
     * TODO建议调用一下查单接口
     * @return boolean
     * @throws Exception
     */
    public function payCancel(){
        $financeStatementOrder = $this->get();
        $statementId = Arrays::value($financeStatementOrder, 'statement_id');
        //调用取消订单接口
        //取消结算
        if($statementId){
            //获取账单信息
            $statementInfo = FinanceStatementService::getInstance($statementId)->get();       
            if((time() - strtotime($statementInfo['create_time'])) < 10 ){
                throw new Exception('账单操作频繁');
            }
            //20220310
            if($statementInfo['has_settle']){
                throw new Exception('账单已结不可操作');
            }
            //单笔账单才处理，多笔不处理
            /*20220310，为了兼容hx往返付款，进行调整，TODO需进行安全性测试
            $con[] = ['statement_id','=',$statementId];
            $orderCount = self::mainModel()->where($con)->count('distinct order_id');            
            if($orderCount > 1){
                throw new Exception('账单'.$statementId.'非单笔支付账单，请联系客服');
            }
            if($statementInfo['order_id'] != $financeStatementOrder['order_id']){
                throw new Exception('订单号不匹配，请联系开发，账单'.$statementId);
            }*/
            // 删除账单
            Db::startTrans();
                // 取消结算
                FinanceStatementService::getInstance($statementId)->update(['has_confirm'=>0]);
                FinanceStatementService::getInstance($statementId)->delete();
            Db::commit();
        }
        return true;
    }
    /**
     * 清除未处理的账单
     * 一般用于订单取消，撤销全部的订单
     * ！！【未测】20210402
     */
    public static function clearOrderNoDeal( $orderId ,$cate = '')
    {
        Debug::debug(__CLASS__.__FUNCTION__,$orderId);
        self::checkTransaction();
        if(!$orderId){
            throw new Exception('订单id必须');
        }
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_statement','=',0];   //未出账单
        $con[] = ['has_settle','=',0];      //未结算
        // 20220615：账单类型
        if($cate){
            $con[] = ['statement_cate','=',$cate];      //账单类型过滤
        }
        //$listFilter = self::mainModel()->where( $con )->select();
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        Debug::debug(__CLASS__.__FUNCTION__.'的$con',$con);
        Debug::debug(__CLASS__.__FUNCTION__.'的$listFilter',$listFilter);
        foreach( $listFilter as $k=>$v){
            self::getInstance( $v['id'])->delete();
        }
    }
    /**
     * 20220619:逐步替代上方clearOrderNoDeal 方法
     * @param type $orderId
     * @param type $cate
     * @throws Exception
     */
    public static function clearOrderNoDealRam( $orderId ,$cate = '')
    {
        Debug::debug(__CLASS__.__FUNCTION__,$orderId);
        if(!$orderId){
            throw new Exception('订单id必须');
        }
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_statement','=',0];   //未出账单
        $con[] = ['has_settle','=',0];      //未结算
        // 20220615：账单类型
        if($cate){
            $con[] = ['statement_cate','=',$cate];      //账单类型过滤
        }
        //$listFilter = self::mainModel()->where( $con )->select();
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
//        dump($listFilter);
        Debug::debug(__CLASS__.__FUNCTION__.'的$con',$con);
        Debug::debug(__CLASS__.__FUNCTION__.'的$listFilter',$listFilter);
        foreach( $listFilter as $k=>$v){
            self::getInstance( $v['id'])->deleteRam();
        }
    }

    /**
     * 20220620:准备替代extraPreSave方法
     * @param type $data
     * @param type $uuid
     */
    public static function ramPreSave(&$data, $uuid) {
        $keys   = ['need_pay_prize','statement_cate','statement_type'];
        //$notices['order_id']          = '订单id必须';        
        $notices['need_pay_prize']    = '金额必须';        
        $notices['statement_cate']    = '对账分类必须';        
        $notices['statement_type']    = '费用类型必须';        
        DataCheck::must($data, $keys,$notices);
        
        $orderId = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');       
        //账单名称：20210319
        $data['company_id']    = OrderService::getInstance($orderId)->fCompanyId();
        //20220608:可外部传入
        $data['statement_name'] = Arrays::value($data, 'statement_name') ? : FinanceStatementService::getStatementNameByOrderId( $orderId , $data['statement_type']);
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        if(!Arrays::value($data, 'change_type')){
            $data['change_type'] =  $needPayPrize >= 0 ? 1 : 2;
        }
        if(Arrays::value($data, 'change_type')){
            if( Arrays::value($data, 'change_type') == 1 ){
                $data['need_pay_prize'] = abs($needPayPrize);//入账，正值
            }
            if( Arrays::value($data, 'change_type') == 2 ){
                $data['need_pay_prize'] = -1 * abs($needPayPrize);//入账，正值
            }
        }
        Debug::debug('$statementCate后的$data',$data);
        if( $orderId ){
            //无缓存取数
            $orderInfo = OrderService::getInstance( $orderId )->get();
            $data['dept_id']    = Arrays::value( $orderInfo , 'dept_id');
            $data['order_type'] = Arrays::value( $orderInfo , 'order_type');
            $data['busier_id']  = Arrays::value( $orderInfo , 'busier_id');
            //statementCate = "";
            //买家
            if($statementCate == "buyer"){
                $data['customer_id']    = Arrays::value( $orderInfo , 'customer_id');
                $data['user_id']        = Arrays::value( $orderInfo , 'user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //卖家
            if($statementCate == "seller"){
                $data['customer_id']    = isset($data['customer_id']) ? $data['customer_id'] : Arrays::value( $orderInfo , 'seller_customer_id');
                $data['user_id']        = isset($data['user_id']) ? $data['user_id'] : Arrays::value( $orderInfo , 'seller_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //推荐人
            if($statementCate == "rec_user" && Arrays::value( $orderInfo , 'rec_user_id')){
                $data['customer_id']    = '';
                $data['user_id']        = Arrays::value( $orderInfo , 'rec_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //业务员
            if($statementCate == "busier" && Arrays::value( $orderInfo , 'busier_id')){
                $data['customer_id']    = '';
                $data['user_id']        = Arrays::value( $orderInfo , 'busier_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
        }
        //有否对账单? 1是，0否
        $data['has_statement']          = Arrays::value( $data , 'statement_id') ? 1 : 0;
        $data['has_settle']             = Arrays::value( $data , 'has_settle') ? 1 : 0;
        $data['ref_statement_order_id'] = Arrays::value( $data , 'ref_statement_order_id') ? Arrays::value( $data , 'ref_statement_order_id') : '';

        $source = OrderService::getInstance($orderId)->fSource();
        //对账单分组
        $data['group'] = $source == 'admin' ? "offline" : "online";     
        //后向关联保存
        //20220622:已有前序账单号，则不需要前序校验判断
        $data['pre_statement_order_id'] = Arrays::value($data, 'pre_statement_order_id') ? : self::preUniSave($data);
        //20220622 ???
        if(!Arrays::value($data, 'customer_id') && !Arrays::value($data, 'user_id')){
            throw new Exception('customer_id和user_id需至少有一个有值');
        }
    }
    
    public static function ramAfterSave(&$data, $uuid) {
        $orderId                = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');
        //订单表的对账字段
        $orderStatementField    = self::getOrderStatementField($statementCate);
        if(OrderService::mainModel()->hasField($orderStatementField)){
            //订单状态更新为已对账
            OrderService::getInstance($orderId)->setUuData([$orderStatementField=>1]);
        }

        OrderService::getInstance($orderId)->objAttrsPush('financeStatementOrder',$data);
        //后向关联保存
        self::afterUniSave($data);
        // 20220620:订单id
        OrderService::getInstance($orderId)->orderDataSyncRam();
    }
    /**
     * 更新后
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterUpdate(&$data, $uuid) {  
        $info           = self::getInstance( $uuid )->get(0);
        $orderId        = Arrays::value( $info , 'order_id');
        $statementId    = Arrays::value( $info , 'statement_id');
        OrderService::getInstance($orderId)->objAttrsUpdate('financeStatementOrder',$uuid, $data);
        if($statementId){
            FinanceStatementService::getInstance($statementId)->objAttrsUpdate('financeStatementOrder',$uuid, $data);
        }
        // 20220620:订单id
        // OrderService::getInstance($orderId)->orderDataSyncRam();
        // 20220624:触发更新动作？？
        OrderService::getInstance($orderId)->updateRam(['status'=>1]);
    }
    /**
     * 前序关联保存
     * @param type $thisData        本次保存的数据
     * @param type $preOrderId      前序订单编号
     */
    public static function preUniSave($data){
        $orderId                = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');        
        //客户账单，才进行前向处理
        if($statementCate != 'buyer'){
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_PRE){
            return '';
        }

        $preOrderInfo = OrderService::getInstance($orderId)->getPreData('pre_order_id');
        //当前订单的销售类型
        $saleType = Arrays::value($preOrderInfo, 'order_type');
        $prizeKey = GoodsPrizeTplService::getPreKey($saleType, $data['statement_type']);
        $resData[DIRECTION]          = DIRECT_PRE;
        $resInfo = self::prizeKeySaveRam($prizeKey, $preOrderInfo['id'], $data['need_pay_prize'], $resData);
        return $resInfo ? $resInfo['id'] : '';
    }
    
    /**
     * 后序关联保存
     * @param type $thisData        本次保存的数据
     * @param type $afterOrderId      后序订单编号
     */
    protected static function afterUniSave($data){
        $orderId                = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');
        //供应商账单，才进行后向处理
        if($statementCate != 'seller'){
            return false;
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_AFT){
            return false;
        }
        $afterOrderArr = OrderService::getInstance($orderId)->getAfterDataArr('pre_order_id');  
        foreach($afterOrderArr as $afterOrderInfo){
            $saleType = Arrays::value($afterOrderInfo, 'order_type');
            //关联后续订单收款
            $prizeKey = GoodsPrizeTplService::getAfterKey($saleType, $data['statement_type']);
            $resData = [];
            $resData['pre_statement_order_id']  = $data['id'];
            $resData[DIRECTION]                 = DIRECT_AFT;
            if($prizeKey){
                self::prizeKeySaveRam($prizeKey, $afterOrderInfo['id'], $data['need_pay_prize'],$resData);
            }
        }
        return true;
    }
    /**
     * 删除前
     */
    public function ramPreDelete(){
        self::queryCountCheck(__METHOD__);
        //有前序关联订单，先删前序
        $info = $this->get();
        $preStatementId = $info['pre_statement_order_id'];
        $tableName  = self::mainModel()->getTable();
        if($preStatementId && !DbOperate::isGlobalDelete($tableName, $preStatementId) ){
            self::getInstance($info['pre_statement_order_id'])->deleteRam();
        }
    }

    public function ramAfterDelete($data){
        //有后序关联订单，再删后序
        $con[]      = ['pre_statement_order_id','=',$this->uuid];
        $afterIds    = self::mainModel()->where($con)->column('id');
        $tableName  = self::mainModel()->getTable();
        Debug::debug();
        foreach($afterIds as $afterId){
            if($afterId && !DbOperate::isGlobalDelete($tableName, $afterId)){
                self::getInstance($afterId)->deleteRam();
            }
        }
        //20220621处理订单数据
        $info = $this->get();
        OrderService::getInstance($info['order_id'])->objAttrsUnSet('financeStatementOrder',$this->uuid);
        OrderService::getInstance($info['order_id'])->orderDataSyncRam();
    }

    /**
     * 由外部直接添加一下退款单（一般用于买了两张退一张的情况）
     */
    public static function addRef($orderId,$refundPrize,$statementType="directRef"){
        self::checkTransaction();
        $data['is_ref']         = 1;
        $data['order_id']       = $orderId;
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
    public static function updateOrderMoney($orderId,$money){
        if(OrderService::getInstance($orderId)->fIsComplete()){
            throw new Exception('订单已结不可操作');
        }
        //20220615增加；
        OrderService::mainModel()->where('id',$orderId)->update(['order_prize'=>$money]);
        $con[] = ['order_id','=',$orderId];
        $statementOrders = self::mainModel()->where($con)->select();
        $statementOrdersArr = $statementOrders ? $statementOrders->toArray() : [];

        $con1[] = ['has_statement','=',1];
        // 20220608，开发向供应商付款的逻辑。增加buyer条件
        $con1[] = ['statement_cate','=','buyer'];
        $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr,$con1),'need_pay_prize'));

        //剩余应付金额
        $remainNeedPayMoney = $money - $hasSettleMoney;
        self::clearOrderNoDeal($orderId);
        if($remainNeedPayMoney > 0){
            //如为应收；
            $prizeKey = "GoodsPrize";
            // 应收
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        } else if($remainNeedPayMoney < 0){
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
    public static function updateOrderMoneyRam($orderId,$money){
//        dump('updateOrderMoneyRam');
//        dump($money);
        if(OrderService::getInstance($orderId)->fIsComplete()){
            throw new Exception('订单已结不可操作');
        }
        $statementCate = 'buyer';
        self::clearOrderNoDealRam($orderId,$statementCate);
        //20220615增加；
        OrderService::getInstance($orderId)->setUuData(['order_prize'=>$money]);
        $statementOrdersArr = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $con1[] = ['has_statement','=',1];
        // 20220608，开发向供应商付款的逻辑。增加buyer条件
        $con1[] = ['statement_cate','=',$statementCate];
        $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr,$con1),'need_pay_prize'));

        //20220624:增加在途
        //$onRoadMoney = self::onRoadSavePrize($orderId, $statementCate);

        //剩余应付金额
        $remainNeedPayMoney = $money - $hasSettleMoney; // - $onRoadMoney;
 
        if($remainNeedPayMoney > 0){
            //如为应收；
            $prizeKey = "GoodsPrize";
            // 应收
            self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney);
        } else if($remainNeedPayMoney < 0){
            // 应退
            $prizeKey = "normalRef";
            self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney);
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
    public static function updateNeedOutcomePrizeRam($orderId,$moneyArr){
        if(OrderService::getInstance($orderId)->fIsComplete()){
            throw new Exception('订单已结不可操作');
        }
        //20220615增加；
        //OrderService::getInstance($orderId)->setUuData(['need_outcome_prize'=>$money]);        
        $statementCate          = 'seller';
        self::clearOrderNoDealRam($orderId,$statementCate);
        $statementOrdersArr = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        //20220622
        //foreach($moneyArr as $customerId=>$money){
        foreach($moneyArr as $mData){
            $con1   = [];
            $con1[] = ['customer_id','=',Arrays::value($mData, 'customer_id')];
            $con1[] = ['user_id','=',Arrays::value($mData, 'user_id')];
            $con1[] = ['has_statement','=',1];
            // 20220608，开发向供应商付款的逻辑。增加seller条件
            $con1[] = ['statement_cate','=',$statementCate];
            $hasSettleMoney         = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr,$con1),'need_pay_prize'));
            //20220624:增加在途
            //$onRoadMoney            = self::onRoadSavePrize($orderId, $statementCate, Arrays::value($mData, 'customer_id'), Arrays::value($mData, 'user_id'));
            //剩余应付金额
            $remainNeedPayMoney     = $mData['need_pay_prize'] - $hasSettleMoney;// - $onRoadMoney;
//            dump('11111111111');
//            dump($mData['need_pay_prize']);
//            dump($hasSettleMoney);
//            dump($onRoadMoney);
//            dump($remainNeedPayMoney);
            
            $savData['customer_id'] = Arrays::value($mData, 'customer_id');
            $savData['user_id']     = Arrays::value($mData, 'user_id');
            if($remainNeedPayMoney < 0){
                //如为应付款；
                $prizeKey = "sellerGoodsPrize";
                self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney,$savData);
            } else if($remainNeedPayMoney > 0){
                // 付款的退款：
                $prizeKey = "sellerNormalRef";
                self::prizeKeySaveRam($prizeKey, $orderId, $remainNeedPayMoney,$savData);
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
    public static function onRoadSavePrize($orderId,$statementCate,$customerId = '',$userId = ''){
        global $glSaveData;
        $tableName  = self::getTable();
        $listsAll   = Arrays::value($glSaveData, $tableName,[]);
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',0];
        $con[] = ['statement_cate','=',$statementCate];
        //付款的可以多个供应商
        if($customerId){
            $con[] = ['customer_id','=',$customerId];
        }
        if($userId){
            $con[] = ['user_id','=',$userId];
        }
        return array_sum(array_column(Arrays2d::listFilter($listsAll, $con),'need_pay_prize'));
    }
    
    /**
     * 20220615：更新订单的应付金额
     */
    public static function updateNeedOutcomePrize($orderId,$money){
        if(OrderService::getInstance($orderId)->fIsComplete()){
            throw new Exception('订单已结不可操作');
        }
        //20220615增加；
        OrderService::mainModel()->where('id',$orderId)->update(['need_outcome_prize'=>$money]);
        $con[] = ['order_id','=',$orderId];
        $statementOrders = self::mainModel()->where($con)->select();
        $statementOrdersArr = $statementOrders ? $statementOrders->toArray() : [];

        $con1[] = ['has_statement','=',1];
        // 20220608，开发向供应商付款的逻辑。增加buyer条件
        $con1[] = ['statement_cate','=','seller'];
        $hasSettleMoney = array_sum(array_column(Arrays2d::listFilter($statementOrdersArr,$con1),'need_pay_prize'));
        //剩余应付金额
        $remainNeedPayMoney = $money - $hasSettleMoney;

        self::clearOrderNoDeal($orderId,'seller');
        if($remainNeedPayMoney > 0){
            //如为应付；
            $prizeKey = "sellerGoodsPrize";
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        } else if($remainNeedPayMoney < 0){
            // 应退
            $prizeKey = "sellerNormalRef";
            self::prizeKeySave($prizeKey, $orderId, $remainNeedPayMoney);
        }
        return $remainNeedPayMoney;
    }

    public function extraPreDelete()
    {
        Debug::debug(__CLASS__.__FUNCTION__);
        self::checkTransaction();
        $info = $this->get();
        if($info['has_statement'] || $info['statement_id']){
            throw new Exception('该明细已生成账单，请先删除账单');
        }
        //删除对账单的明细
        if( Arrays::value( $info , 'has_settle')){
            throw new Exception('账单已结不可操作');
        }
        $orderId                = Arrays::value($info, 'order_id');
        $statementCate          = Arrays::value($info, 'statement_cate');        
        //订单表的对账字段
        $orderStatementField    = self::getOrderStatementField($statementCate);
        if(OrderService::mainModel()->hasField( $orderStatementField )){
            //订单状态更新为未对账
            OrderService::mainModel()->where('id',$orderId)->update([$orderStatementField=>0]);
        }
        //20220630：删除增加操作；解决订单写入的退款金额bug
        OrderService::getInstance($orderId)->objAttrsUnSet('financeStatementOrder',$this->uuid);
    }
    
        /**
     * 删除价格数据
     */
    public function extraAfterDelete()
    {
//        //退款订单
//        if(Arrays::value( $info , 'ref_statement_order_id')){
//            self::getInstance( Arrays::value( $info , 'ref_statement_order_id') )->setHasRef();
//        }      
    }
    /**
     * 账单id，触发订单流程
     * 一般用于账单结账后触发订单
     */
    public static function statementIdTriggerOrderFlow($statementId){
        self::checkTransaction();
//        $con[] = ['statement_id','=',$statementId];
//        $orderIds = self::mainModel()->where($con)->column('distinct order_id');
        $lists = FinanceStatementService::getInstance($statementId)->objAttrsList('financeStatementOrder');
        $orderIds = array_unique(array_column($lists,'order_id'));
        Debug::debug('FinanceStatementOrderService触发关联订单动作',$orderIds);
        //触发动作
        foreach($orderIds as $orderId){
            // Db::startTrans();
            OrderFlowNodeService::lastNodeFinishAndNext($orderId);
            // Db::commit();
        }
    }

    /**
     * 对账单商品id
     */
    public static function statementGoodsName( $statementId )
    {
        $con[]      = ['statement_id','=',$statementId];
        return self::conGoodsName($con);
    }
    
    /**
     * 对账单商品id
     */
    public static function statementOrderGoodsName( $ids )
    {
        $con[]      = ['id','in',$ids];
        return self::conGoodsName($con);
    }
    /**
     * 条件取商品名
     * @param type $con
     * @return type
     */
    protected static function conGoodsName($con=[]){
        $idSql = FinanceStatementOrderService::mainModel()->where($con)->field('distinct order_id')->buildSql();
        $orderTable = OrderService::getTable();
        $sql = '( SELECT `goods_name` FROM `'.$orderTable.'` WHERE  `id` in ' . $idSql . ') ';
        $res = Db::query($sql);
        return implode(',', array_column($res, 'goods_name'));
    }
    /**
     * 
     * @param type $data
     * @param type $uuid
     */
    public static function extraPreSave(&$data, $uuid) {
        $keys   = ['need_pay_prize','statement_cate','statement_type'];
        //$notices['order_id']          = '订单id必须';        
        $notices['need_pay_prize']    = '金额必须';        
        $notices['statement_cate']    = '对账分类必须';        
        $notices['statement_type']    = '费用类型必须';        
        DataCheck::must($data, $keys,$notices);
        //账单名称：20210319
        $data['company_id']    = OrderService::getInstance($data['order_id'])->fCompanyId();
        //20220608:可外部传入
        $data['statement_name'] = Arrays::value($data, 'statement_name') ? : FinanceStatementService::getStatementNameByOrderId( $data['order_id'] , $data['statement_type']);
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        if(!Arrays::value($data, 'change_type')){
            $data['change_type'] =  $needPayPrize >= 0 ? 1 : 2;
        }
        if(Arrays::value($data, 'change_type')){
            if( Arrays::value($data, 'change_type') == 1 ){
                $data['need_pay_prize'] = abs($needPayPrize);//入账，正值
            }
            if( Arrays::value($data, 'change_type') == 2 ){
                $data['need_pay_prize'] = -1 * abs($needPayPrize);//入账，正值
            }
        }
        $orderId = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');       
        Debug::debug('$statementCate后的$data',$data);
        if( $orderId ){
            //无缓存取数
            $orderInfo = OrderService::getInstance( $orderId )->get();
            $data['dept_id']    = Arrays::value( $orderInfo , 'dept_id');
            $data['order_type'] = Arrays::value( $orderInfo , 'order_type');
            $data['busier_id']  = Arrays::value( $orderInfo , 'busier_id');
            //statementCate = "";
            //买家
            if($statementCate == "buyer"){
                $data['customer_id']    = Arrays::value( $orderInfo , 'customer_id');
                $data['user_id']        = Arrays::value( $orderInfo , 'user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //卖家
            if($statementCate == "seller"){
                $data['customer_id']    = isset($data['customer_id']) ? $data['customer_id'] : Arrays::value( $orderInfo , 'seller_customer_id');
                $data['user_id']        = isset($data['user_id']) ? $data['user_id'] : Arrays::value( $orderInfo , 'seller_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //推荐人
            if($statementCate == "rec_user" && Arrays::value( $orderInfo , 'rec_user_id')){
                $data['customer_id']    = '';
                $data['user_id']        = Arrays::value( $orderInfo , 'rec_user_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
            //业务员
            if($statementCate == "busier" && Arrays::value( $orderInfo , 'busier_id')){
                $data['customer_id']    = '';
                $data['user_id']        = Arrays::value( $orderInfo , 'busier_id');
                FinanceManageAccountService::addManageAccountData($data);
            }
        }
        //有否对账单? 1是，0否
        $data['has_statement']          = Arrays::value( $data , 'statement_id') ? 1 : 0;
        $data['ref_statement_order_id'] = Arrays::value( $data , 'ref_statement_order_id') ? Arrays::value( $data , 'ref_statement_order_id') : '';
        //退款订单
        if(Arrays::value( $data , 'ref_statement_order_id')){
            if(self::mainModel()->where('ref_statement_order_id',Arrays::value( $data , 'ref_statement_order_id'))->find()){
                //20220617:似乎是一个扯淡的更新？？
                self::getInstance( Arrays::value( $data , 'ref_statement_order_id') )->setHasRef();
            }
        }
        $source = OrderService::getInstance($orderId)->fSource();
        //对账单分组
        $data['group'] = $source == 'admin' ? "offline" : "online";        
    }

    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        self::checkTransaction();
        $orderId                = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');
        //订单表的对账字段
        $orderStatementField    = self::getOrderStatementField($statementCate);
        if(OrderService::mainModel()->hasField($orderStatementField)){
            //订单状态更新为已对账
            OrderService::mainModel()->where('id',$orderId)->update([$orderStatementField=>1]);
        }
        // 写入对象实例
        //$info = self::getInstance($uuid)->get(MASTER_DATA);
        OrderService::getInstance($orderId)->objAttrsPush('financeStatementOrder',$data);
        //20220615:处理前序订单的价格：根据需付金额。
        $orderInfo = OrderService::getInstance($orderId)->get();
        if($orderInfo && $orderInfo['pre_order_id']){
            $buyerPrize = self::getBuyerPrize($orderId);
            self::updateNeedOutcomePrize($orderInfo['pre_order_id'], $buyerPrize);
        }
        //20220619：收款的，关联前序订单一笔付款；
        //付款的；自动关联后续订单一笔收款；
        
        
        
    }
    /**
     * 价格key保存
     * @param type $prizeKey    价格key
     * @param type $orderId     订单id
     * @param type $prize       价格
     */
    public static function prizeKeySave( $prizeKey, $orderId, $prize ,$data=[]){
        //判断订单有key不执行
        Debug::debug('addFinanceStatementOrder的$prizeKey',$prizeKey);
        // 有价格才写入
        if(!$prize){
            return false;
        }
        $goodsPrizeInfo         = GoodsPrizeKeyService::getByPrizeKey( $prizeKey );  //价格key取归属
        //key不可重复添加时，判断有key不执行
        if(Arrays::value($goodsPrizeInfo,'is_duplicate')){
            if(self::hasStatementOrder($orderId, $prizeKey )){
                return false;
            }
        }

        $prizeKeyRole           = GoodsPrizeKeyService::keyBelongRole( $prizeKey );
        $data['order_id']       = $orderId;
        $data['change_type']    = Arrays::value($goodsPrizeInfo,'change_type') ;
        $data['statement_cate'] = $prizeKeyRole;  //价格key取归属
        $data['need_pay_prize'] = $data['change_type'] == 1 ?  abs($prize) : -1 * abs($prize);
        $data['statement_type'] = $prizeKey;
        //增加是否退款的判断
        $data['is_ref'] = Arrays::value($goodsPrizeInfo,'type') == 'ref' ? 1 :  0;
        Debug::debug('【最终添加】addFinanceStatementOrder，的data',$data);
        return self::save( $data );
    }
    /**
     * 20220619 价格key保存
     * @param type $prizeKey    价格key
     * @param type $orderId     订单id
     * @param type $prize       价格
     */
    public static function prizeKeySaveRam( $prizeKey, $orderId, $prize ,$data=[]){
        // 有价格才写入
        if(!$prize || !$prizeKey){
            return false;
        }
        $goodsPrizeInfo         = GoodsPrizeKeyService::getByPrizeKey( $prizeKey );  //价格key取归属
        //key不可重复添加时，判断有key不执行
        if(Arrays::value($goodsPrizeInfo,'is_duplicate')){
            if(self::hasStatementOrder($orderId, $prizeKey )){
                return false;
            }
        }
        $prizeKeyRole           = GoodsPrizeKeyService::keyBelongRole( $prizeKey );
        $data['order_id']       = $orderId;
        $data['change_type']    = Arrays::value($goodsPrizeInfo,'change_type') ;
        $data['statement_cate'] = $prizeKeyRole;  //价格key取归属
        $data['need_pay_prize'] = $data['change_type'] == 1 ?  abs($prize) : -1 * abs($prize);
        $data['statement_type'] = $prizeKey;
        //增加是否退款的判断
        $data['is_ref'] = Arrays::value($goodsPrizeInfo,'type') == 'ref' ? 1 :  0;
        Debug::debug('【最终添加】addFinanceStatementOrder，的data',$data);

        return self::saveRam( $data );
    }
    /**
     * 更新退款状态
     */
    public function setHasRef()
    {
        $con[]  = ['ref_statement_order_id','=',$this->uuid]; 
        $count  = FinanceStatementOrderService::count( $con );
        $hasRef = $count ? 1 : 0 ; 
        $this->update(['has_ref'=>$hasRef]);
    }
    /**
     * 根据订单id和价格key，查已结算价格
     */
    public static function hasSettleMoney( $orderId, $prizeKeys )
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['statement_type','in',$prizeKeys];
        $con[] = ['has_settle','=',1];
        
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        return array_sum(array_column($listFilter,'need_pay_prize'));
    }
    /**
     * 价格账单是否已存在
     * @param type $orderId
     * @param type $prizeKeys
     * @return type
     */
    public static function hasStatementOrder( $orderId, $prizeKeys )
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['statement_type','=',$prizeKeys];
        
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        return count($listFilter);
        //return self::count($con);
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        // 20220609:结算状态是否发生改变：用于extraAfterUpdate进行触发更新
        // 有传参，才判断，没传参，认为未发生改变
        $data['settleChange'] = isset($data['has_settle']) ? Arrays::value($data, 'has_settle') != Arrays::value($info, 'has_settle') : false;
        
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__.__FUNCTION__,$data);
        self::checkTransaction();
        $info       = self::getInstance( $uuid )->get(0);
        $orderId    = Arrays::value( $info , 'order_id');
        //订单的金额更新0923：归集集中更新
        //self::orderMoneyUpdate($orderId);
        //是退款单的，把退款金额结算一下
        $refStatementOrderId = Arrays::value( $info , 'ref_statement_order_id');
        
        if($refStatementOrderId && self::mainModel()->where('ref_statement_order_id',$refStatementOrderId)->find()){
            $con[] = ['ref_statement_order_id','=',$refStatementOrderId];
            $con[] = ['has_settle','=',1];
            $money = self::sum($con, 'need_pay_prize');
            self::mainModel()->where('id',$refStatementOrderId)->update(['ref_prize'=>$money]);
            //更新退款字段的金额
            //self::getInstance( $refStatementOrderId )->update(['ref_prize'=>$money]);   //订单的退款金额
        }
        //[20220518]节点信息更新
        OrderService::getInstance($orderId)->objAttrsUpdate('financeStatementOrder',$uuid, $data);
        //20220320，适用于部分收款后，更新订单的相应金额（流程节点不往前走）
        //20220515取消has_settle == 1判断（考虑人工录入错误的情况）。
        OrderService::getInstance($orderId)->orderDataSync();        

        //20211004尝试去除
//        if(Arrays::value( $data , 'has_settle') == 1){
//            //重新校验未结账单的金额，20210407
//            self::reCheckNoSettle($orderId);
//        }
        //结算状态有变，才进行处理，20220617：出账怎么办？？？
        if($data['settleChange'] && Arrays::value( $data , 'has_settle') == 1){
            //20220615:如果有前序订单，自动针对前序订单进行结算
            $orderInfo      = OrderService::getInstance($orderId)->get();
            if($orderInfo && $orderInfo['pre_order_id'] && $info['statement_cate'] = 'buyer'){
                $accountId = FinanceStatementService::getInstance($info['statement_id'])->getAccountId();
                // 财务入账
                PackLogic::financeIncomeAdm($orderInfo['pre_order_id'], $accountId, $info['need_pay_prize']);
            }
        }
    }
    
    public static function extraDetail(&$item, $uuid) {
        if(!$item){ return false;}
        $orderId            = Arrays::value( $item,"order_id" );
        $item['fGoodsName'] = OrderService::getInstance($orderId)->fGoodsName();
        return $item;
    }
    /**
     * 20220615 获取应收客户的金额
     * 0615:已结定数
     */
    public static function getBuyerPrize($orderId,$onlySettle = true){
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $con[] = ['order_id','in',$orderId];
        $con[] = ['statement_cate','=','buyer'];
        if($onlySettle){
            $con[] = ['has_settle','=',1];
        }
        return array_sum(array_column(Arrays2d::listFilter($listsAll, $con),'need_pay_prize'));
    }

    /**
     * 20220615 获取应付供应商的金额
     * 0615:已结定数
     */
    public static function getSellerPrize($orderId, $onlySettle = true){
        $con[] = ['order_id','in',$orderId];
        $con[] = ['statement_cate','=','seller'];
        if($onlySettle){
            $con[] = ['has_settle','=',1];
        }
        return self::sum($con, 'need_pay_prize');
    }
    /**
     * 重新校验未结算账单的金额
     * （进行修改）
     */
    public static function reCheckNoSettle( $orderId )
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',0];
        $lists = self::lists( $con );
        foreach( $lists as $value){
            Debug::debug('reCheckNoSettle的循环value',$value);
            $prize       = OrderService::getInstance( $orderId )->prizeKeyGetPrize( $value['statement_type'] );
            Debug::debug('reCheckNoSettle的循环$prize',$prize);
            //更新未结账单金额
            if($prize){
                self::getInstance( $value['id'])->update(['need_pay_prize'=>$prize]);
                Debug::debug('reCheckNoSettle的循环$prize',$prize);
            } else if( $value['is_ref'] == 0 ){
                //20210511 线上退款bug，增加退款不删
                //【没有价格】：直接把账单删了；加个锁
                // 20210424 测试到手工录入的价格bug，增加“未出账单”条件
                $delCon     = [];
                $delCon[]   = ['id','=',$value['id']];
                $delCon[]   = ['has_statement','=',0];    //未出账单
                $delCon[]   = ['has_settle','=',0];
                // TODO 延长定金有bug，暂时隐藏20210520，再考虑收公证定金的情况【】
//                self::mainModel()->where( $delCon )->delete();
            }
        }
    }
    /**
     * 根据订单查询应付账单
     */
    public static function needPayStatementOrderIds($orderId, $changeType=1,$statementCate = 'buyer'){
        $con[] = ['order_id','in',$orderId];
        $con[] = ['change_type','=',$changeType];
        $con[] = ['statement_cate','=',$statementCate];
        $con[] = ['has_settle','=',0];
        return self::mainModel()->where($con)->column('id');
    }
    
    /**
     * 获取账单id，无记录时生成账单
     * @param type $reGenerate  已有未结账单是否重新生成
     * @return type
     * @throws Exception
     */
    public static function getStatementIdWithGenerate($statementOrderIds, $reGenerate = false ){
        if(!is_array($statementOrderIds)){
            $statementOrderIds = [$statementOrderIds];
        }
        $con[] = ['id','in',$statementOrderIds];
        $statementOrderInfos = self::listSetUudata($con,MASTER_DATA);
        Debug::debug('getStatementIdWithGenerate调试打印',$statementOrderInfos);
       
        $statementIds = [];
        // 多个账单循环取消
        foreach($statementOrderIds as $statementOrderId){
            //有账单直接取账单号
            $info = self::getInstance($statementOrderId)->get(MASTER_DATA);
            if(!$info){
                throw new Exception('账单'.$statementOrderId.'不存在');
            }
            $statementIds[$statementOrderId] = $info['statement_id'];
            // 有账单；未结；重新生成
            if($info['statement_id'] && !$info['has_settle'] && $reGenerate){
                //时间判断
                $time = session('lastPayWxTime') ? : 0;
                $timeNew = time();
                if ($timeNew - $time <= 10){
                    throw new Exception('操作频繁，请稍后再试'.($timeNew - $time));
                }

                //20220301:微信支付尝试批量处理,批量请求有bug20220302
                WechatWxPayLogService::dealBatch();
                //self::checkNoTransaction();
                // 取消账单
                // 20220302暂时注释
                // Db::startTrans();
                self::getInstance($statementOrderId)->payCancel();
                // Db::commit();
                $statementIds[$statementOrderId] = '';
            }
        }

        //明细对应多个账单
        if(count(array_unique($statementIds)) > 1){
            throw new Exception('账单明细对应了多个账单，部分已结无法取消，请联系开发');
        }

        $uniqIds = array_unique($statementIds);
        $statementId = $uniqIds ?  array_pop($uniqIds) : '';
        if(!$statementId){
            //重新生成账单
            $financeStatement = FinanceStatementService::statementGenerate( $statementOrderIds );
            $statementId = $financeStatement['id'];
        }
        return $statementId;
    }
    
    /**
     * 空账单设定对账单id
     */
    public function setStatementId( $statementId )
    {
        $info = $this->get(0);
        if(Arrays::value($info, 'statement_id')){
            throw new Exception( $this->uuid.'已经对应了对账单'. Arrays::value($info, 'statement_id') );
        }
        return $this->update([ 'statement_id'=>$statementId,"has_statement"=>1 ]);
    }
    /**
     * 20220620
     */
    public function setStatementIdRam($statementId){
        $info = $this->get(0);
        if(Arrays::value($info, 'statement_id')){
            throw new Exception( $this->uuid.'已经对应了对账单'. Arrays::value($info, 'statement_id') );
        }
        return $this->updateRam([ 'statement_id'=>$statementId,"has_statement"=>1 ]);
    }
    /**
     * 取消对账单id
     * @param type $statementId
     * @return type
     * @throws Exception
     */
    public function cancelStatementId()
    {
        $info = $this->get(0);
        if(Arrays::value($info, 'has_settle')){
            throw new Exception( $this->uuid.'已经结算过了' );
        }
        if(Arrays::value($info, 'has_confirm')){
            throw new Exception( $this->uuid.'客户已经确认过了' );
        }
        return $this->update([ 'statement_id'=>"","has_statement"=>0]);
    }
    
    public function cancelStatementIdRam()
    {
        $info = $this->get(0);
        if(Arrays::value($info, 'has_settle')){
            throw new Exception( $this->uuid.'已经结算过了' );
        }
        if(Arrays::value($info, 'has_confirm')){
            throw new Exception( $this->uuid.'客户已经确认过了' );
        }
        return $this->updateRam([ 'statement_id'=>"","has_statement"=>0]);
    }
    
    /**
     * 订单表的对账字段
     */
    private static function getOrderStatementField($statementCate)
    {
        return 'has_'. ($statementCate ? $statementCate.'_' : '') .'statement';
    }
    
    /*
     * 20220320尝试复原方法
    public function delete()
    {
        self::checkTransaction();
        //删除对账单的明细
        $info = $this->get(0);
        if( Arrays::value( $info , 'has_settle')){
            throw new Exception('账单已结不可操作');
        }        
        $orderId                = Arrays::value($info, 'order_id');
        $statementCate          = Arrays::value($info, 'statement_cate');        
        //订单表的对账字段
        $orderStatementField    = self::getOrderStatementField($statementCate);
        if(OrderService::mainModel()->hasField( $orderStatementField )){
            //订单状态更新为未对账
            OrderService::mainModel()->where('id',$orderId)->update([$orderStatementField=>0]);
        }
        //删除对账订单。
        $res = $this->commDelete();
        //订单的金额更新
        //self::orderMoneyUpdate($orderId);
        //退款订单
        if(Arrays::value( $info , 'ref_statement_order_id')){
            self::getInstance( Arrays::value( $info , 'ref_statement_order_id') )->setHasRef();
        }
        return $res;
    }*/
    
    /**
     * 订单账单列表
     * @param type $orderId
     * @param type $con
     * @return type
     */
//    public static function orderStatementLists($orderId, $con = [] ){
//        $con[] = ['order_id','=',$orderId];
//        $res = self::mainModel()->where($con)->select();
//        return $res ? $res->toArray() : [] ;
//    }
    /**
     * 统计订单已付金额
     * @param type $orderId
     * @return type
     */
//    protected static function orderSettleMoneyCalc( $orderId ,$con = [])
//    {
//        $con[] = ['order_id','=',$orderId];
//        $con[] = ['has_settle','=',1];
//        return self::mainModel()->where($con)->sum( 'need_pay_prize' );
//    }
    /**
     * 订单金额更新
     */
    /*
    public static function orderMoneyUpdate( $orderId )
    {
        $dataArr = self::orderMoneyData($orderId);
        Debug::debug('orderMoneyUpdate的$dataArr',$dataArr);
        //更新金额
        if($dataArr){            
            OrderService::getInstance($orderId)->update( $dataArr );
        }
    }
    */
    /**
     * 20220620:基于内存的订单金额数据
     * @param type $orderId
     * @return type
     */
    public static function orderMoneyData( $orderId )
    {
        //收款
        $lists = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $conPay[] = ['statement_cate','=','buyer'];
        $conPay[] = ['change_type','=','1'];
        $conPay[] = ['has_settle','=','1'];
        $dataArr['pay_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conPay),'need_pay_prize'));
        //付款
        $conOutcome[] = ['statement_cate','=','seller'];
        $conOutcome[] = ['change_type','=','2'];
        $conOutcome[] = ['has_settle','=','1'];
        $dataArr['outcome_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conOutcome),'need_pay_prize'));
        //收退
        $conRef[] = ['statement_cate','=','buyer'];
        $conRef[] = ['change_type','=','2'];
        $conRef[] = ['has_settle','=','1'];
        $dataArr['refund_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conRef),'need_pay_prize'));
        //付退
        $conOutcomeRef[] = ['statement_cate','=','seller'];
        $conOutcomeRef[] = ['change_type','=','1'];
        $conOutcomeRef[] = ['has_settle','=','1'];
        $dataArr['outcome_refund_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conOutcomeRef),'need_pay_prize'));
        //支出
        $conCost[] = ['statement_cate','=','cost'];
        $conCost[] = ['has_settle','=','1'];
        $dataArr['cost_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conCost),'need_pay_prize'));
        //毛利
        $conFinal[] = ['has_settle','=','1'];
        $dataArr['final_prize'] = array_sum(array_column(Arrays2d::listFilter($lists, $conFinal),'need_pay_prize'));

        return $dataArr;
    }
    
//    /*
//     * 订单是否已对账
//     * TODO 优化 一笔订单在一个客户下只对账一次。
//     */
//    public static function hasStatement( $customerId, $orderId )
//    {
//        $con[] = ['customer_id','=',$customerId];
//        $con[] = ['order_id','=',$orderId];
//        return self::mainModel()->where( $con )->value('id');
//    }
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
     * 应付金额
     */
    public function fNeedPayPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单id
     */
    public function fOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单金额
     */
    public function fOrderPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已付金额
     */
    public function fPayPrize() {
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
     * 对账单id
     */
    public function fStatementId() {
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

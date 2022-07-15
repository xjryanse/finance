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
class FinanceStatementService {
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
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
        'financeStatementOrder'=>[
            'class'     =>'\\xjryanse\\finance\\service\\FinanceStatementOrderService',
            'keyField'  =>'statement_id',
            'master'    =>true
        ],
        'financeAccountLog'=>[
            'class'     =>'\\xjryanse\\finance\\service\\FinanceAccountLogService',
            'keyField'  =>'statement_id',
            'master'    =>true
        ]
    ];
    
    /**
     * 获取账单未结清的金额
     * 一般用于组合支付中进行业务逻辑处理
     */
    public function remainMoney(){
        $info = $this->get();
        // 总金额
        $totalMoney     = $info['need_pay_prize'];
        // 已结清金额
        $finishMoney    = FinanceAccountLogService::statementFinishMoney( $this->uuid );
        // -300 -50
        // 解决浮点数运算TODO更优？
        return (intval( $totalMoney * 100 ) - intval( $finishMoney * 100)) * 0.01;
    }
    
    /**
     * 直冲用户账户（微信分账，用户余额）
     * 
     */
    public function doDirect(){
        $con    = [];
        $con[]  = ['statement_id','=',$this->uuid];
        $statementOrderInfoCount = FinanceStatementOrderService::count($con);
        if(!$statementOrderInfoCount){
            throw new Exception("账单".$this->uuid."没有账单明细");
        }
        if($statementOrderInfoCount > 1){
            throw new Exception("不支持多明细账单直接结算".$this->uuid."共计".$statementOrderInfoCount."笔");
        }
        $statementOrderInfo = FinanceStatementOrderService::find( $con );
        $goodsPrizeInfo         = GoodsPrizeKeyService::getByPrizeKey( Arrays::value( $statementOrderInfo, 'statement_type') );  //价格key取归属
        //如果是充值到余额的，直接处理
        if( Arrays::value($goodsPrizeInfo,'to_money') == "money" ){
            //收款操作
            return UserPayLogic::collect( $this->uuid, FR_FINANCE_MONEY );
        }
        //如果是分账的，也直接处理
        if( Arrays::value($goodsPrizeInfo,'to_money') == "sec_share" ){
            return UserPayLogic::secCollect( $this->uuid, FR_FINANCE_WECHAT );
        }
    }
    
    /**
     * 清除未处理的账单
     * 一般用于订单取消，撤销全部的订单
     * ！！【未测】20210402
     */
    public static function clearOrderNoDeal( $orderId )
    {
        Debug::debug(__CLASS__.__FUNCTION__,$orderId);
        self::checkTransaction();
        if(!$orderId){
            throw new Exception('订单id必须');
        }
        //20220311:账单id从明细取
        $cond[] = ['order_id','in',$orderId];
        $statementIds = FinanceStatementOrderService::mainModel()->where($cond)->column('statement_id');
        $con[] = ['id','in',$statementIds];
        //20220311到这里
        // $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',0];      //未结算
        $lists = self::mainModel()->where( $con )->select();
        Debug::debug(__CLASS__.__FUNCTION__.'的$con',$con);
        Debug::debug(__CLASS__.__FUNCTION__.'的$lists',$lists);
        foreach( $lists as $k=>$v){
            //设为未对账
            self::getInstance( $v['id'])->update(['has_confirm'=>0]);
            //然后才能删
            self::getInstance( $v['id'])->delete();
        }
    }
    /**
     * 20220622
     * @param type $orderId
     * @throws Exception
     */
    public static function clearOrderNoDealRam( $orderId )
    {
        if(!$orderId){
            throw new Exception('订单id必须');
        }
        //20220311:账单id从明细取
        $cond[] = ['order_id','in',$orderId];
        $statementIds = FinanceStatementOrderService::mainModel()->where($cond)->column('statement_id');
        $con[] = ['id','in',$statementIds];
        //20220311到这里
        // $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',0];      //未结算
        $lists = self::mainModel()->where( $con )->select();
        foreach( $lists as $k=>$v){
            //设为未对账
            self::getInstance( $v['id'])->updateRam(['has_confirm'=>0]);
            //然后才能删
            self::getInstance( $v['id'])->deleteRam();
        }
    }

    /**
     * 根据订单明细id，生成账单
     * @param type $statementOrderIds   账单订单表的id
     * @return type
     */
    public static function statementGenerate($statementOrderIds = [])
    {
        //self::checkNoTransaction();
        //字符串数组都可以传
        $data['statementOrderIds']  = $statementOrderIds 
                ? (is_array($statementOrderIds) ? $statementOrderIds: [$statementOrderIds])
                : [];
        $data['has_confirm']        = 1;    //默认已确认
        $orderIds = FinanceStatementOrderService::column('distinct order_id',[['id','in',$statementOrderIds]]);
        $source = OrderService::mainModel()->where([['id','in',$orderIds]])->column('distinct source');
        if(in_array('admin',$source)){
            $data['group'] = 'offline'; //线下
        } else {
            $data['group'] = 'online';  //线上
        }
        Db::startTrans();
        $res = FinanceStatementService::save( $data );
        Db::commit();

        return $res;
    }
    
    public static function paginate( $con = [],$order='',$perPage=10,$having = '')
    {
        $res = self::commPaginate($con, $order, $perPage, $having);
        //【当前页合计】
        $sumCurrent = 0;
        foreach( $res['data'] as $key=>$value){
            $sumCurrent += $value['need_pay_prize'];
        }

        $sumTotal = self::sum( $res['con'], 'need_pay_prize');
        //统计数据描述
        $res['staticsDescribe'] = "本页合计：".$sumCurrent."，全部合计：".$sumTotal;

        return $res;
    }
    
    /**
     * 单订单生成对账单名称
     * @param type $orderId
     */
    public static function getStatementNameByOrderId( $orderId, $statementType )
    {
        //商品名称加上价格的名称
        $orderInfo          = OrderService::getInstance( $orderId )->get(0);
        $fGoodsCate         = Arrays::value($orderInfo, 'goods_cate');
        $statementName      = $fGoodsCate ? $fGoodsCate.'-' : '';
        $fGoodsName         = Arrays::value($orderInfo, 'goods_name');
        $goodsPrizeInfo     = GoodsPrizeKeyService::getByPrizeKey($statementType);
        $statementName      .= $fGoodsName ." ". Arrays::value($goodsPrizeInfo, 'prize_name');
        return $statementName;
    }
    /**
     * 获取账号id
     */
    public function getAccountId(){
        $con[] = ['statement_id','=',$this->uuid];
        return FinanceAccountLogService::mainModel()->where($con)->value('account_id');
    }
    
    public static function extraDetail(&$item, $uuid) {
        if(!$item){ return false;}
        $manageAccountId = Arrays::value( $item , 'manage_account_id');
        //管理账户余额
        $info = FinanceManageAccountService::getInstance( $manageAccountId )->get(0);
        $item['manageAccountMoney'] = Arrays::value( $info , 'money');
        //合同订单:逗号分隔
        $con[]      = ['statement_id','=',$uuid]; 
        $orderIds   = FinanceStatementOrderService::mainModel()->where( $con )->column('order_id');
        $item->SCorder_id   = count($orderIds);         //订单数量
        $item->Dorder_id    = implode(',', $orderIds);  //订单逗号分隔
        
        return $item;
    }
    /**
     * 额外详情
     * @param type $ids
     * @return type
     */
    public static function extraDetails( $ids ){
        //数组返回多个，非数组返回一个
        $isMulti = is_array($ids);
        if(!is_array($ids)){
            $ids = [$ids];
        }
        $con[] = ['id','in',$ids];
        $listRaw = self::mainModel()->where($con)->select();
        $lists = $listRaw ? $listRaw->toArray() : [];

        $statementOrderCountArr = FinanceStatementOrderService::groupBatchCount('statement_id', $ids);

        foreach($lists as &$v){
            //明细数
            $v['statementOrderCount']  = Arrays::value($statementOrderCountArr, $v['id'],0);
            //20220609:是否有订单号；用于控制按钮显示
            $v['hasOrderId']    = $v['order_id'] ? 1:0;
        }

        return $isMulti ? $lists : $lists[0];
    }
    
    public static function save( $data )
    {
        self::checkTransaction();
        //无单条订单，无订单数组
        if(!Arrays::value($data, 'order_id') && !Arrays::value($data, 'orders') && !Arrays::value($data, 'statementOrderIds')){
            throw new Exception('请选择订单');
        }
        //转为数组存
        if(is_string(Arrays::value($data, 'order_id')) && Arrays::value($data, 'order_id')){
            $cateKey            = Arrays::value($data, 'statement_type');
            $data['statement_name'] = self::getStatementNameByOrderId( $data['order_id'] , $cateKey);
        }
        $res = self::commSave($data);
        //转为数组存
        if(is_string(Arrays::value($data, 'order_id')) && Arrays::value($data, 'order_id')){
            //单笔订单的存法
            $data['orders'] = [$data];
        }
        //【TODO优化2021-03-03】创建对账订单明细。
        if(isset($data['orders'])){
            foreach($data['orders'] as &$value){
                $value['belong_cate']       = Arrays::value($res, 'belong_cate');
                $value['user_id']           = Arrays::value($res, 'user_id');
                $value['manage_account_id'] = Arrays::value($res, 'manage_account_id');
                $value['customer_id']       = Arrays::value($res, 'customer_id');
                $value['statement_id']      = $res['id'];
                $value['statement_cate']    = Arrays::value($res, 'statement_cate');
    //            if(FinanceStatementOrderService::hasStatement( $customerId, $value['order_id'] )){
    //                throw new Exception('订单'.$value['order_id'] .'已经对账过了');
    //            }
                //一个一个添，有涉及其他表的状态更新
                FinanceStatementOrderService::save($value);
            }
        }
        return $res;
    }
    
    public function delete()
    {
        self::checkTransaction();
        $info = $this->get(0);
        if( Arrays::value($info, 'has_confirm') ){
            throw new Exception('客户已确认对账，不可删');
        }
        //删除对账单的明细
        $con[] = ['statement_id','=',$this->uuid];
        $statementOrders = FinanceStatementOrderService::lists( $con );
        foreach( $statementOrders as $value){
            //【TODO】一个个删，可能涉及状态更新
//            FinanceStatementOrderService::getInstance($value['id'])->delete();
            //只是把应收的取消了
            FinanceStatementOrderService::getInstance($value['id'])->cancelStatementId();
        }
        return $this->commDelete();
    }
    
    public static function extraPreSave(&$data, $uuid) {
        //【关联已有对账单明细】
        if(isset( $data['statementOrderIds'])){
            //对账订单笔数
            $statementOrderIdCount = count($data['statementOrderIds']);
            $cond[] = ['id','in',$data['statementOrderIds']] ;
            $manageAccountIds = FinanceStatementOrderService::mainModel()->where( $cond )->column('distinct manage_account_id');
            if(count($manageAccountIds) >1){
                throw new Exception('请选择同一个客户的账单');
            }
            $data['goods_name'] = $data['statementOrderIds'] ? FinanceStatementOrderService::statementOrderGoodsName($data['statementOrderIds']) : ''; 
            //更新对账单订单的账单id
            foreach( $data['statementOrderIds'] as $value){
                //财务账单-订单；
                FinanceStatementOrderService::getInstance( $value )->setStatementId( $uuid );
            }
            //应付金额
            $data['need_pay_prize']  = FinanceStatementOrderService::mainModel()->where( $cond )->sum('need_pay_prize');
            //弹一个
            $statementOrderId = array_pop( $data['statementOrderIds'] );
            $statementOrderInfo         = FinanceStatementOrderService::getInstance( $statementOrderId )->get(0);
            Debug::debug('FinanceStatementService 的 $statementOrderInfo',$statementOrderInfo);

            $data['customer_id']        = Arrays::value($statementOrderInfo, 'customer_id');
            //[20220518]增加部门
            $data['dept_id']            = Arrays::value($statementOrderInfo, 'dept_id');
            $data['belong_cate']        = Arrays::value($statementOrderInfo, 'belong_cate');
            $data['statement_cate']     = Arrays::value($statementOrderInfo, 'statement_cate');
            $data['user_id']            = Arrays::value($statementOrderInfo, 'user_id');
            $data['manage_account_id']  = Arrays::value($statementOrderInfo, 'manage_account_id');
            $data['statement_name']     = Arrays::value($statementOrderInfo, 'statement_name');
            $data['busier_id']          = Arrays::value($statementOrderInfo, 'busier_id');
            //if($statementOrderIdCount == 1){
            $data['order_id']       = Arrays::value($statementOrderInfo, 'order_id');
            //}
            if($statementOrderIdCount > 1){
                $data['statement_name'] .= " 等".$statementOrderIdCount."笔";
            }
        }
        
        Debug::debug('$data',$data);
        //步骤1
        $needPayPrize = Arrays::value($data, 'need_pay_prize');
        //生成变动类型
        if(!Arrays::value($data, 'change_type')){
            $data['change_type'] =  $needPayPrize >= 0 ? 1 : 2;
        }
        //步骤2
        $customerId   = Arrays::value($data, 'customer_id');        
        $userId       = Arrays::value($data, 'user_id');        
        Debug::debug('$customerId',$customerId);
        Debug::debug('$userId',$userId);
        /*管理账户id*/
        if($customerId){
            $data['belong_cate'] = 'customer';  //账单归属：单位
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $data['belong_cate'] = 'user';      //账单归属：个人
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $data['manage_account_id'] = $manageAccountId;
        //有订单，拿推荐人
        $orderId = Arrays::value($data, 'order_id');
        if( $orderId ){
            $orderInfo = OrderService::getInstance( $orderId )->get(0);
            $data['order_type'] = Arrays::value( $orderInfo , 'order_type');
            $data['busier_id']  = Arrays::value( $orderInfo , 'busier_id');
        }
        //20210430 客户的应付、供应商的应收，为退款;1应收，2应付
        if((Arrays::value($data, 'change_type') == '1' && Arrays::value($data, 'statement_cate')=='seller')
                || (Arrays::value($data, 'change_type') == '2' && Arrays::value($data, 'statement_cate')=='buyer') ){
            $data['is_ref'] = 1;
            $data['statement_name'] = '退款 '.$data['statement_name'];
        } else {
            $data['is_ref'] = 0;
        }
        if($orderId){
            $source = OrderService::getInstance($orderId)->fSource();
            //对账单分组
            $data['group'] = $source == 'admin' ? "offline" : "online";        
        }
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        // 20220609:结算状态是否发生改变：用于extraAfterUpdate进行触发更新
        // 有传参，才判断，没传参，认为未发生改变
        $data['settleChange'] = isset($data['has_settle']) ? Arrays::value($data, 'has_settle') != Arrays::value($info, 'has_settle') : false;

//        
//        $hasSettleNew   = Arrays::value($data, 'has_settle');
//        $accountLogId   = Arrays::value($data, 'account_log_id');
//        if( $hasSettle ){
//            self::getInstance($uuid)->settle( $accountLogId );
//        } else {
//            self::getInstance($uuid)->cancelSettle();
//        }
    }
    /*
     * 更新商品名称
     */
    public static function extraAfterSave(&$data, $uuid) {
//        $goodsName = FinanceStatementOrderService::statementGoodsName($uuid);
//        return self::mainModel()->where('id',$uuid)->update(['goods_name'=>$goodsName]);
    }
    
    public static function extraAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__.__FUNCTION__,$data);
        //20220319，从preUpdate搬迁到AfterUpdate
        self::checkTransaction();
        //20220609，尝试修复bug（结算不删账单，导致脏数据）；if(isset($data['has_settle']) && $hasSettleRaw != $data['has_settle'] ){
        if($data['settleChange']){
            if( $data['has_settle'] ){
                $accountLogId   = Arrays::value($data, 'account_log_id');
                self::getInstance($uuid)->settle( $accountLogId );
            } else {
                self::getInstance($uuid)->cancelSettle();
            }
        }

        $upData = [];
        if(isset($data['has_confirm'])){
            $upData['has_confirm'] = Arrays::value($data, 'has_confirm');
        }
        if(isset($data['has_settle'])){
            $upData['has_settle'] = Arrays::value($data, 'has_settle');
        }
        if($upData){
            $con[] = ['statement_id','=',$uuid ];
            //20220320，启动触发模式
            $statementOrderIds = FinanceStatementOrderService::mainModel()->where($con)->column('id');
            foreach($statementOrderIds as &$statementOrderId){
                FinanceStatementOrderService::getInstance($statementOrderId)->update($upData);
            }
        }
    }
    /**
     * 20220620 前序订单保存
     * @param type $data
     * @param type $uuid
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        $statementOrderIds = Arrays::value($data, 'statementOrderIds',[]);
        //【关联已有对账单明细】
        if($statementOrderIds){
            //对账订单笔数
            $statementOrderIdCount = count($statementOrderIds);
            //提取账单，TODO优化
            $manageAccountIds = [];
            $needPayPrize = 0;
            foreach($statementOrderIds as $statementOrderId){
                $info = FinanceStatementOrderService::getInstance($statementOrderId)->get();
                //20220620
                self::getInstance($uuid)->objAttrsPush('financeStatementOrder',$info);
                $manageAccountIds[] = $info['manage_account_id'];
                $needPayPrize += $info['need_pay_prize'];
            }
            if(count(array_unique($manageAccountIds)) >1){
                throw new Exception('请选择同一个客户的账单');
            }
            $data['goods_name'] = $statementOrderIds ? FinanceStatementOrderService::statementOrderGoodsName($statementOrderIds) : ''; 
            //更新对账单订单的账单id
            foreach( $statementOrderIds as $value){
                //财务账单-订单；
                FinanceStatementOrderService::getInstance( $value )->setStatementIdRam( $uuid );
            }
            //应付金额
            $data['need_pay_prize']  = $needPayPrize;
            //弹一个
            $statementOrderId = array_pop( $statementOrderIds );
            $statementOrderInfo         = FinanceStatementOrderService::getInstance( $statementOrderId )->get(0);
            Debug::debug('FinanceStatementService 的 $statementOrderInfo',$statementOrderInfo);

            $data['customer_id']        = Arrays::value($statementOrderInfo, 'customer_id');
            //[20220518]增加部门
            $data['dept_id']            = Arrays::value($statementOrderInfo, 'dept_id');
            $data['belong_cate']        = Arrays::value($statementOrderInfo, 'belong_cate');
            $data['statement_cate']     = Arrays::value($statementOrderInfo, 'statement_cate');
            $data['user_id']            = Arrays::value($statementOrderInfo, 'user_id');
            $data['manage_account_id']  = Arrays::value($statementOrderInfo, 'manage_account_id');
            $data['statement_name']     = Arrays::value($statementOrderInfo, 'statement_name');
            $data['busier_id']          = Arrays::value($statementOrderInfo, 'busier_id');
            //if($statementOrderIdCount == 1){
            $data['order_id']       = Arrays::value($statementOrderInfo, 'order_id');
            //}
            if($statementOrderIdCount > 1){
                $data['statement_name'] .= " 等".$statementOrderIdCount."笔";
            }
        }
        Debug::debug('$data',$data);
        //生成变动类型
        if(!Arrays::value($data, 'change_type')){
            $data['change_type'] =  $needPayPrize >= 0 ? 1 : 2;
        }
        //步骤2
        $customerId   = Arrays::value($data, 'customer_id');        
        $userId       = Arrays::value($data, 'user_id');        
        /*管理账户id*/
        if($customerId){
            $data['belong_cate'] = 'customer';  //账单归属：单位
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $data['belong_cate'] = 'user';      //账单归属：个人
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        $data['manage_account_id'] = $manageAccountId;
        //有订单，拿推荐人
        $orderId = Arrays::value($data, 'order_id');
        if( $orderId ){
            $orderInfo = OrderService::getInstance( $orderId )->get(0);
            $data['order_type'] = Arrays::value( $orderInfo , 'order_type');
            $data['busier_id']  = Arrays::value( $orderInfo , 'busier_id');
        }
        //20210430 客户的应付、供应商的应收，为退款;1应收，2应付
        if((Arrays::value($data, 'change_type') == '1' && Arrays::value($data, 'statement_cate')=='seller')
                || (Arrays::value($data, 'change_type') == '2' && Arrays::value($data, 'statement_cate')=='buyer') ){
            $data['is_ref'] = 1;
            $data['statement_name'] = '退款 '.$data['statement_name'];
        } else {
            $data['is_ref'] = 0;
        }
        $dealDirection = Arrays::value($data, 'DIRECTION');
        //前向处理
        if($orderId && (!$dealDirection || $dealDirection == 'pre')){
            $source = OrderService::getInstance($orderId)->fSource();
            //对账单分组
            $data['group'] = $source == 'admin' ? "offline" : "online";                    
        }
        
        //后向关联保存
        $data['pre_statement_id'] = Arrays::value($data, 'pre_statement_id') ? : self::preUniSave($data);
    }
    
    public static function ramAfterSave(&$data, $uuid) {
        //后向关联保存
        self::afterUniSave($data);        
    }
    /**
     * 20220620
     * @param type $data
     */
    public function preUniUpdate($data){
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_PRE){
            return '';
        }
        $preInfo = $this->getPreData('pre_statement_id');
        if(!$preInfo){
            return false;
        }
        //20220622:结算由accountLog触发；取消结算才进行关联处理
        if(isset($data['has_settle']) && !$data['has_settle']){
            $updData = Arrays::getByKeys($data, ['has_settle','has_confirm']);
            $updData[DIRECTION]          = DIRECT_PRE;
            return self::getInstance($preInfo['id'])->updateRam($updData);
        }
    }
    /**
     * 20220620
     * @param type $data
     */
    public function afterUniUpdate($data){
        //无指向，或指向为后向，才进行处理
        
        $dealDirection = Arrays::value($data, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_AFT){
            return false;
        }
        $afterDataArr = $this->getAfterDataArr('pre_statement_id');
        if(!$afterDataArr){
            return false;
        }
        //20220622:结算由accountLog触发；取消结算才进行关联处理
        if(isset($data['has_settle']) && !$data['has_settle']){
            $updData = Arrays::getByKeys($data, ['has_settle','has_confirm']);
            $updData[DIRECTION]          = DIRECT_AFT;
            foreach($afterDataArr as $afterData){
                self::getInstance($afterData['id'])->updateRam($updData);
            }
        }
        return true;
    }
    /**
     * 前序关联保存
     * @param type $thisData        本次保存的数据
     * @param type $preOrderId      前序订单编号
     */
    public static function preUniSave($thisData){
        $statementOrderIds      = Arrays::value($thisData, 'statementOrderIds',[]);
        $preStatementOrderIds   = [];
        foreach($statementOrderIds as $statementOrderId){
            $info = FinanceStatementOrderService::getInstance($statementOrderId)->get();
            if(!$info['pre_statement_order_id']){
                continue;
            }
            $preStatementOrderInfo = FinanceStatementOrderService::getInstance($info['pre_statement_order_id'])->get();
            if($preStatementOrderInfo){
                $preStatementOrderIds[] = $preStatementOrderInfo['id']; 
            }
        }
        
        if(!$preStatementOrderIds){
            return '';
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($thisData, DIRECTION);        
        if($dealDirection && $dealDirection != DIRECT_PRE){
            return '';
        }
        
        $data['statementOrderIds']  = $preStatementOrderIds;
        $data['has_confirm']        = 1;
        //20220620:处理方向：向前
        $data['DIRECTION']          = DIRECT_PRE;
        $resData = self::saveRam( $data );
        return $resData ? $resData['id'] :"";
    }
    
    public static function afterUniSave($data){
        $orderId                = Arrays::value($data, 'order_id');
        $statementCate          = Arrays::value($data, 'statement_cate');
        if(!$orderId) {
            return false;
        }
        //供应商账单，才进行后向处理
        if($statementCate != 'seller'){
            return false;
        }
        //无指向，或指向为后向，才进行处理
        $dealDirection = Arrays::value($data, DIRECTION);
        if($dealDirection && $dealDirection != DIRECT_AFT){
            return false;
        }
        //
        $afterOrderArr = OrderService::getInstance($orderId)->getAfterDataArr('pre_order_id');  

        foreach($afterOrderArr as $afterOrderInfo){
            $statementOrderIds   = [];
            $statementOrders = OrderService::getInstance($afterOrderInfo['id'])->objAttrsList('financeStatementOrder');
            foreach($statementOrders as $statementOrder){
                if(!Arrays::value($statementOrder, 'has_statement')){
                    $statementOrderIds[] = $statementOrder['id']; 
                }
            }
            if($statementOrderIds){
                $savData = [];
                $savData['statementOrderIds']  = $statementOrderIds;
                $savData['has_confirm']        = 1;
                //20220620:处理方向：向前
                $savData[DIRECTION]          = DIRECT_AFT;
                $savData['pre_statement_id']  = $data['id'];
                self::saveRam( $savData );
            }
        }
        return true;
/*************************/
    }
    
    public static function ramPreUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        // 20220609:结算状态是否发生改变：用于extraAfterUpdate进行触发更新
        // 有传参，才判断，没传参，认为未发生改变
        $data['settleChange'] = isset($data['has_settle']) ? Arrays::value($data, 'has_settle') != Arrays::value($info, 'has_settle') : false;
        
        self::getInstance($uuid)->preUniUpdate($data);
    }
    
    public static function ramAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__.__FUNCTION__,$data);
        //20220609，尝试修复bug（结算不删账单，导致脏数据）；if(isset($data['has_settle']) && $hasSettleRaw != $data['has_settle'] ){
        if($data['settleChange']){
            if( $data['has_settle'] ){
                $accountLogId   = Arrays::value($data, 'account_log_id');
                self::getInstance($uuid)->settleRam( $accountLogId );
            } else {
                self::getInstance($uuid)->cancelSettleRam();
            }
        }

        $upData = [];
        if(isset($data['has_confirm'])){
            $upData['has_confirm'] = Arrays::value($data, 'has_confirm');
        }
        if(isset($data['has_settle'])){
            $upData['has_settle'] = Arrays::value($data, 'has_settle');
        }
        if($upData){
            $con[] = ['statement_id','=',$uuid ];
            //20220320，启动触发模式
            $statementOrderIds = FinanceStatementOrderService::mainModel()->where($con)->column('id');
            foreach($statementOrderIds as &$statementOrderId){
                FinanceStatementOrderService::getInstance($statementOrderId)->updateRam($upData);
            }
        }
        
        self::getInstance($uuid)->afterUniUpdate($data);  
    }
    
    public function ramPreDelete(){
        self::queryCountCheck(__METHOD__);
        //有前序关联订单，先删前序
        $info = $this->get();
        $preStatementId = $info['pre_statement_id'];
        $tableName  = self::mainModel()->getTable();
        if($preStatementId && !DbOperate::isGlobalDelete($tableName, $preStatementId) ){
            self::getInstance($info['pre_statement_id'])->deleteRam();
        }

        //清除明细的账单
        $statementOrders  = $this->objAttrsList('financeStatementOrder');
        foreach( $statementOrders as $value){
            //只是把应收的取消了
            FinanceStatementOrderService::getInstance($value['id'])->cancelStatementIdRam();
        }
    }

    public function ramAfterDelete($data){
        //有后序关联订单，再删后序
        $con[]      = ['pre_statement_id','=',$this->uuid];
        $afterIds    = self::mainModel()->where($con)->column('id');
        $tableName  = self::mainModel()->getTable();
        foreach($afterIds as $afterId){
            if($afterId && !DbOperate::isGlobalDelete($tableName, $afterId)){
                self::getInstance($afterId)->deleteRam();
            }
        }
    }
    /**
     * 前序关联删除
     */
    public function preUniDelete(){
        
    }
    /**
     * 后续关联删除
     */
    public function afterUniDelete(){
        
    }
    
    /**
     * 对冲结算逻辑
     * FinanceManageAccountLog登记一笔冲账记录
     * 更新本表has_settle 字段为1；
     * 更新FinanceStatementOrder的has_settle字段为1；
     */
    protected function settle( $accountLogId = '')
    {
        self::checkTransaction();
        if(FinanceManageAccountLogService::hasLog(self::mainModel()->getTable(), $this->uuid)){
            return false;
        }
        $info = $this->get();
        $hasConfirm     = Arrays::value($info, 'has_confirm');  //客户已确认
        if(!$hasConfirm){
            throw new Exception('请先进行客户确认，才能冲账，对账单号:'.$this->uuid);
        }
        $customerId     = Arrays::value($info, 'customer_id');
        $userId         = Arrays::value($info, 'user_id');
        $needPayPrize   = Arrays::value($info, 'need_pay_prize');   //正-他欠我，负-我欠他
        //扣减对冲账户余额
        if($customerId){
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }        
        $manageAccountInfo  = FinanceManageAccountService::getInstance( $manageAccountId )->get(0);        
        $manageAccountMoney = Arrays::value($manageAccountInfo, 'money');   //账户余额
        
        if(!$accountLogId){
            $con        = [];
            $con[]      = ['statement_id','=',$this->uuid];
            $accountLog = FinanceAccountLogService::find( $con );
            $accountLogId = Arrays::value($accountLog, 'id');
        }
        
        if($needPayPrize > 0){
            $data['change_type'] = 2;   //客户出账，我进账，客户金额越来越少
            if($manageAccountMoney<$needPayPrize && !$accountLogId){
                throw new Exception('客户账户余额(￥'. $manageAccountMoney  .')不足，请先收款');
            }
        } else {
            $data['change_type'] = 1;   //客户进账，我出账，客户金额越来越多
            if($manageAccountMoney > $needPayPrize && !$accountLogId){
                throw new Exception('该客户当前已付款(￥'. abs($manageAccountMoney)  .')不足，请先付款');
            }
        }
        $data['manage_account_id'] = $manageAccountId;
        $data['money']          = Arrays::value($info, 'need_pay_prize');
        $data['from_table']     = self::mainModel()->getTable();
        $data['from_table_id']  = $this->uuid;      
        $data['reason']         = Arrays::value($info, 'statement_name') . ' 冲账';
        //登记冲账
        FinanceManageAccountLogService::save($data);
        $stateData['has_settle'] = 1;
        if( $accountLogId ){
            $stateData['account_log_id'] = $accountLogId;
        }
        $res = self::mainModel()->where('id',$this->uuid)->update( $stateData );   //更新为已结算
//        //冗余：20220617，似乎可以取消？？？
        $con[] = ['statement_id','=',$this->uuid];
        $lists = FinanceStatementOrderService::lists( $con );
        foreach( $lists as $v){
            FinanceStatementOrderService::getInstance( $v['id'] )->update(['has_settle'=>1]);   //更新为已结算
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
    protected function settleRam( $accountLogId = '')
    {
        if(FinanceManageAccountLogService::hasLog(self::mainModel()->getTable(), $this->uuid)){
            return false;
        }
        $info = $this->get();
        $hasConfirm     = Arrays::value($info, 'has_confirm');  //客户已确认
        if(!$hasConfirm){
            throw new Exception('请先进行客户确认，才能冲账，对账单号:'.$this->uuid);
        }
        $customerId     = Arrays::value($info, 'customer_id');
        $userId         = Arrays::value($info, 'user_id');
        $needPayPrize   = Arrays::value($info, 'need_pay_prize');   //正-他欠我，负-我欠他
        //扣减对冲账户余额
        if($customerId){
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        } else {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }        
        $manageAccountInfo  = FinanceManageAccountService::getInstance( $manageAccountId )->get(0);        
        $manageAccountMoney = Arrays::value($manageAccountInfo, 'money');   //账户余额
        
        if(!$accountLogId){
            $con        = [];
            $con[]      = ['statement_id','=',$this->uuid];
            $accountLog = FinanceAccountLogService::find( $con );
            $accountLogId = Arrays::value($accountLog, 'id');
        }
        
        if($needPayPrize > 0){
            $data['change_type'] = 2;   //客户出账，我进账，客户金额越来越少
            if($manageAccountMoney<$needPayPrize && !$accountLogId){
                //20220622:内部调费用有bug
                //throw new Exception('客户账户余额(￥'. $manageAccountMoney  .')不足，请先收款');
            }
        } else {
            $data['change_type'] = 1;   //客户进账，我出账，客户金额越来越多
            if($manageAccountMoney > $needPayPrize && !$accountLogId){
                //20220622:内部调费用有bug
                //throw new Exception('该客户当前已付款(￥'. abs($manageAccountMoney)  .')不足，请先付款');
            }
        }
        $data['manage_account_id'] = $manageAccountId;
        $data['money']          = Arrays::value($info, 'need_pay_prize');
        $data['from_table']     = self::mainModel()->getTable();
        $data['from_table_id']  = $this->uuid;      
        $data['reason']         = Arrays::value($info, 'statement_name') . ' 冲账';
        //登记冲账
        FinanceManageAccountLogService::saveRam($data);
        $stateData['has_settle'] = 1;
        if( $accountLogId ){
            $stateData['account_log_id'] = $accountLogId;
        }
        //$res = self::mainModel()->where('id',$this->uuid)->update( $stateData );   //更新为已结算
        $res = $this->updateRam($stateData);
//        //冗余：20220617，似乎可以取消？？？
        $lists = $this->objAttrsList('financeStatementOrder');
        foreach( $lists as $v){
            FinanceStatementOrderService::getInstance( $v['id'] )->updateRam(['has_settle'=>1]);   //更新为已结算
        }
        //20220516，使用上述方法在ydzb有性能问题，下述方法无法触发更新。TODO，寻求更优解决方案？？
//        $con[] = ['statement_id','=',$this->uuid ];
//        FinanceStatementOrderService::mainModel()->where($con)->update(['has_settle'=>1]);        
        return $res;
    }
    /**
     * 取消对冲结算逻辑
     */
    protected function cancelSettle()
    {
        self::checkTransaction();
        $con[]  =   ['from_table','=',self::mainModel()->getTable()];
        $con[]  =   ['from_table_id','=',$this->uuid];
        $lists = FinanceManageAccountLogService::lists($con);
        foreach( $lists as $v){
            //一个个删，可能关联其他的删除
            FinanceManageAccountLogService::getInstance($v['id'])->delete();
        }
        //步骤2：
        $res = self::mainModel()->where('id',$this->uuid)->update(['has_settle'=>0,"account_log_id"=>""]);   //更新为未结算
        //20220516：调整，以触发触发器，TODO解决性能问题 20220617，似乎可以取消？？？
        $cone[] = ['statement_id','=',$this->uuid];
        $listsOrder = FinanceStatementOrderService::lists( $cone );
        foreach( $listsOrder as $v){
            FinanceStatementOrderService::getInstance( $v['id'] )->update(['has_settle'=>0]);   //更新为未结算
        }        
        //20220516注释
        //FinanceStatementOrderService::mainModel()->where('statement_id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        
        //步骤3：【关联删入账】20210319关联删入账
        $con2[] = ['statement_id','=',$this->uuid];
        $listsAccountLog = FinanceAccountLogService::lists( $con2 );
        foreach( $listsAccountLog as $v){
            //一个个删，可能关联其他的删除
            FinanceAccountLogService::getInstance($v['id'])->delete();
        }
        return $res;
    }
    
    /**
     * 取消对冲结算逻辑
     */
    protected function cancelSettleRam()
    {
        $con[]  =   ['from_table','=',self::mainModel()->getTable()];
        $con[]  =   ['from_table_id','=',$this->uuid];
        $lists = FinanceManageAccountLogService::lists($con);
        foreach( $lists as $v){
            //一个个删，可能关联其他的删除
            FinanceManageAccountLogService::getInstance($v['id'])->deleteRam();
        }
        //步骤2：
        //$res = self::mainModel()->where('id',$this->uuid)->update(['has_settle'=>0,"account_log_id"=>""]);   //更新为未结算
        $res = $this->updateRam(['has_settle'=>0,"account_log_id"=>""]);
        //20220516：调整，以触发触发器，TODO解决性能问题 20220617，似乎可以取消？？？
        $cone[] = ['statement_id','=',$this->uuid];
        $listsOrder = FinanceStatementOrderService::lists( $cone );
        foreach( $listsOrder as $v){
            FinanceStatementOrderService::getInstance( $v['id'] )->updateRam(['has_settle'=>0]);   //更新为未结算
        }        
        //20220516注释
        //FinanceStatementOrderService::mainModel()->where('statement_id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        
        //步骤3：【关联删入账】20210319关联删入账
        $con2[] = ['statement_id','=',$this->uuid];
        $listsAccountLog = FinanceAccountLogService::lists( $con2 );
        foreach( $listsAccountLog as $v){
            //一个个删，可能关联其他的删除
            FinanceAccountLogService::getInstance($v['id'])->deleteRam();
        }
        return $res;
    }
    /**
     * 退款账单自动关联一笔付款账单
     * 获取当前账单id，若当前账单已关联退款单，或不是退款账单，则返回
     * 当前账单有多笔订单，不支持关联原始账单 - ？？？
     * 
     */
    public function refUni()
    {
        $info = $this->get();
        Debug::debug('退款账单信息',$info);        
        if($info['ref_statement_id']){
            return false;
        }
        if(!$info['is_ref']){
            throw new Exception($this->uuid.'不是退款账单');
        }
        if(!$info['order_id']){
            throw new Exception('仅支持单订单账单');
        }
        //20220311:账单id从明细取
        $cond[] = ['order_id','=',$info['order_id']];
        $statementIds = FinanceStatementOrderService::mainModel()->where($cond)->column('statement_id');
        $con[] = ['id','in',$statementIds];
        //剩余金额大于等于当前账单金额，且金额从小到大排列，取第一条记录
        // $con[] = ['order_id','=',$info['order_id']];
        $con[] = ['is_ref','=',0];  //非退款订单
        $con[] = ['statement_cate','=',$info['statement_cate']];
        $con[] = ['remainPrize','>=',$info['need_pay_prize']];      //虚拟计算字段，版本5.7以上
        $incomeInfo = self::mainModel()->where( $con )->find();
        if(!$incomeInfo){
            throw new Exception('没有匹配的付款账单');
        }
        $data['ref_statement_id'] = $incomeInfo['id'];
        $this->update( $data );
    }
    /**
     * 通过微信渠道进行退款；
     * 需要原付款单通过微信支付
     */
    public function refWxPay(){
        //关联一下付款账单
        Db::startTrans();
        $this->refUni(); 
        Db::commit();
        //从主库读取数据
        $info = self::mainModel()->master()->get($this->uuid);
        if(!$info['order_id']){
            throw new Exception('线上退款仅支持单订单，请重新生成对账单');
        }
        if(!$info['ref_statement_id']){
            throw new Exception($this->uuid.'未指定原付款账单');
        }
        $payInfo    = self::getInstance( $info['ref_statement_id'] )->get(0);
        if(!$payInfo['account_log_id']){
            throw new Exception($this->uuid.'原付款账单无支付流水记录');
        }
        //原付款单的支付记录
        $payLogInfo = FinanceAccountLogService::getInstance( $payInfo['account_log_id'] )->get(0);
        if($payLogInfo['from_table'] != "w_wechat_wx_pay_log"){
            throw new Exception('原付款账单非微信支付，不可使用微信支付渠道退款');
        }
        $con    = [];
        $con[]  = ['company_id','=',$info['company_id']];
        $payConf = WechatWxPayConfigService::mainModel()->where( $con )->find();
        Debug::debug('$payConf',$payConf);
        //TODO待调整20210225
        $thirdPayParam['wePubAppId']    = Arrays::value($payConf, 'AppId');
        $thirdPayParam['openid']        = WechatWxPayLogService::getInstance($payLogInfo['from_table_id'])->fOpenid();
        //国通支付
        //执行退款操作
        $ress                = UserPayLogic::ref($this->uuid, FR_FINANCE_WECHAT, $thirdPayParam);
        
        if($ress['return_code'] == 'SUCCESS' && $ress['result_code'] == 'SUCCESS'){
            return $ress;
        } else {
            $errMsg = $ress['return_msg'] == 'OK' ? $ress['err_code_des'] : $ress['return_msg'];
            throw new Exception('退款渠道报错：'.$errMsg);
        }
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

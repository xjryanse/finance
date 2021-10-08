<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Debug;
use xjryanse\order\service\OrderService;
use xjryanse\order\service\OrderGoodsService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\goods\service\GoodsPrizeKeyService;
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
            //单笔账单才处理，多笔不处理
            $con[] = ['statement_id','=',$statementId];
            $orderCount = self::mainModel()->where($con)->count('distinct order_id');            
            if($orderCount > 1){
                throw new Exception('账单'.$statementId.'非单笔支付账单，请联系客服');
            }
            if($statementInfo['order_id'] != $financeStatementOrder['order_id']){
                throw new Exception('订单号不匹配，请联系开发，账单'.$statementId);
            }
            // 取消结算
            FinanceStatementService::getInstance($statementId)->update(['has_confirm'=>0]);
            // 删除账单
            FinanceStatementService::getInstance($statementId)->delete();
        }
        return true;
    }    
    /**
     * 清除未处理的账单
     * 一般用于订单取消，撤销全部的订单
     * ！！【未测】20210402
     */
    public static function clearOrderNoDeal( $orderId )
    {
        self::checkTransaction();
        if(!$orderId){
            throw new Exception('订单id必须');
        }
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_statement','=',0];   //未出账单
        $con[] = ['has_settle','=',0];      //未结算
        //$lists = self::mainModel()->where( $con )->select();
        $listsAll = OrderService::getInstance($orderId)->objAttrsList('financeStatementOrder');
        $listFilter = Arrays2d::listFilter($listsAll, $con);
        foreach( $listFilter as $k=>$v){
            self::getInstance( $v['id'])->delete();
        }
    }

    public function extraPreDelete()
    {
        self::checkTransaction();
        $info = $this->get();
        if($info['has_statement'] || $info['statement_id']){
            throw new Exception('该明细已生成账单，请先删除账单');
        }
    }
    /**
     * 账单id，触发订单流程
     * 一般用于账单结账后触发订单
     */
    public static function statementIdTriggerOrderFlow($statementId){
//        $con[] = ['statement_id','=',$statementId];
//        $orderIds = self::mainModel()->where($con)->column('distinct order_id');
        $lists = FinanceStatementService::getInstance($statementId)->objAttrsList('financeStatementOrder');
        $orderIds = array_unique(array_column($lists,'order_id'));
        Debug::debug('FinanceStatementOrderService触发关联订单动作',$orderIds);
        //触发动作
        foreach($orderIds as $orderId){
            Db::startTrans();
            OrderFlowNodeService::lastNodeFinishAndNext($orderId);
            Db::commit();
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
    
    public static function extraPreSave(&$data, $uuid) {
        $keys   = ['order_id','need_pay_prize','statement_cate','statement_type'];
        $notices['order_id']          = '订单id必须';        
        $notices['need_pay_prize']    = '金额必须';        
        $notices['statement_cate']    = '对账分类必须';        
        $notices['statement_type']    = '费用类型必须';        
        DataCheck::must($data, $keys,$notices);
        //账单名称：20210319
        $data['company_id']    = OrderService::getInstance($data['order_id'])->fCompanyId();
        $data['statement_name'] = FinanceStatementService::getStatementNameByOrderId( $data['order_id'] , $data['statement_type']);
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
        if( $orderId ){
            //无缓存取数
            $orderInfo = OrderService::getInstance( $orderId )->get();
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
                $data['customer_id']    = Arrays::value( $orderInfo , 'seller_customer_id');
                $data['user_id']        = Arrays::value( $orderInfo , 'seller_user_id');
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
    }
    /**
     * 价格key保存
     */
    /**
     * 
     * @param type $prizeKey    价格key
     * @param type $orderId     订单id
     * @param type $prize       价格
     */
    public static function prizeKeySave( $prizeKey, $orderId, $prize ){
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
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
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
        //20211004尝试去除
//        if(Arrays::value( $data , 'has_settle') == 1){
//            //重新校验未结账单的金额，20210407
//            self::reCheckNoSettle($orderId);
//        }
    }
    
    public static function extraDetail(&$item, $uuid) {
        if(!$item){ return false;}
        $orderId            = Arrays::value( $item,"order_id" );
        $item['fGoodsName'] = OrderService::getInstance($orderId)->fGoodsName();
        return $item;
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
                // 取消账单
                Db::startTrans();
                self::getInstance($statementOrderId)->payCancel();
                Db::commit();
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
    
    /**
     * 订单表的对账字段
     */
    private static function getOrderStatementField($statementCate)
    {
        return 'has_'. ($statementCate ? $statementCate.'_' : '') .'statement';
    }

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
    }
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
     * 订单金额数据
     */
    public static function orderMoneyData( $orderId )
    {
        $field['pay_prize']     = "ifnull(SUM( if(`statement_cate` = 'buyer' and `change_type` = 1,`need_pay_prize`,0 )),0) AS pay_prize";
        $field['outcome_prize'] = "ifnull(SUM( if(`statement_cate` = 'seller' and `change_type` = 2,`need_pay_prize`,0 )),0) AS outcome_prize";
        $field['refund_prize']  = "ifnull(SUM( if(`statement_cate` = 'buyer' and `change_type` = 2,`need_pay_prize`,0 )),0) AS refund_prize";
        $field['outcome_refund_prize'] = "ifnull(SUM( if(`statement_cate` = 'seller' and `change_type` = 1,`need_pay_prize`,0 )),0) AS outcome_refund_prize";
        $field['cost_prize']    = "ifnull(SUM( if(`statement_cate` = 'cost',`need_pay_prize`,0 )),0) AS cost_prize";
        $field['final_prize']   = "ifnull(SUM( `need_pay_prize` ),0) AS final_prize";
        
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',1];
        $data = self::mainModel()->where($con)->field(implode(',', $field))->find();
        $dataArr = $data ? $data->toArray() : [];
        Debug::debug('orderMoneyUpdate的$dataArr',$dataArr);
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

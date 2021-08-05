<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Debug;
use xjryanse\order\service\OrderService;
use Exception;
/**
 * 收款单-订单关联
 */
class FinanceStatementOrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatementOrder';
    
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
        $lists = self::mainModel()->where( $con )->select();
        foreach( $lists as $k=>$v){
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
     * 对账单商品id
     */
    public static function statementGoodsName( $statementId )
    {
        $con[]      = ['statement_id','=',$statementId];
        $orderIds   = FinanceStatementOrderService::column('order_id',$con);
        $con1[]     = ['id','in',$orderIds];
        $goodsName  = OrderService::column('goods_name',$con1);
        return implode(',', $goodsName);
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
            $orderInfo = OrderService::getInstance( $orderId )->get(0);
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
            //订单的金额更新
            self::orderMoneyUpdate($orderId);
        }
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
        
        return self::sum($con, 'need_pay_prize');
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        self::checkTransaction();
        $info       = self::getInstance( $uuid )->get(0);
        $orderId    = Arrays::value( $info , 'order_id');
        //订单的金额更新
        self::orderMoneyUpdate($orderId);
        //是退款单的，把退款金额结算一下
        $refStatementOrderId = Arrays::value( $info , 'ref_statement_order_id');
//        dump('$refStatementOrderId');
//        dump( $refStatementOrderId );
//        dump( self::mainModel()->where('ref_statement_order_id',$refStatementOrderId)->find() );
//        dump('$refStatementOrderId-结束');
        
        if($refStatementOrderId && self::mainModel()->where('ref_statement_order_id',$refStatementOrderId)->find()){
            $con[] = ['ref_statement_order_id','=',$refStatementOrderId];
            $con[] = ['has_settle','=',1];
            $money = self::sum($con, 'need_pay_prize');
//            dump('$refStatementOrderId-内部');
//            dump( $refStatementOrderId );
            //更新退款字段的金额
            self::getInstance( $refStatementOrderId )->update(['ref_prize'=>$money]);   //订单的退款金额
        }
        if(Arrays::value( $data , 'has_settle') == 1){
            //重新校验未结账单的金额，20210407
            self::reCheckNoSettle($orderId);
        }
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
        self::orderMoneyUpdate($orderId);
        //退款订单
        if(Arrays::value( $info , 'ref_statement_order_id')){
            self::getInstance( Arrays::value( $info , 'ref_statement_order_id') )->setHasRef();
        }
        return $res;
    }
    
    /**
     * 统计订单已付金额
     * @param type $orderId
     * @return type
     */
    protected static function orderSettleMoneyCalc( $orderId ,$con = [])
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',1];
        return self::mainModel()->where($con)->sum( 'need_pay_prize' );
    }
    /**
     * 订单金额更新
     */
    protected static function orderMoneyUpdate( $orderId )
    {
        //已收金额
        $con1[] = ['statement_cate','=','buyer'];
        $con1[] = ['change_type','=','1'];
        $data["pay_prize"]      = self::orderSettleMoneyCalc( $orderId, $con1);
        //已付金额
        $con2[] = ['statement_cate','=','seller'];
        $con2[] = ['change_type','=','2'];
        $data["outcome_prize"]  = self::orderSettleMoneyCalc( $orderId, $con2 );
        //收退金额
        $con3[] = ['statement_cate','=','buyer'];  //买家，出账
        $con3[] = ['change_type','=','2'];
        $data["refund_prize"]  = self::orderSettleMoneyCalc( $orderId, $con3 );
        //付退金额
        $con4[] = ['statement_cate','=','seller'];  //卖家，入账
        $con4[] = ['change_type','=','1'];
        $data["outcome_refund_prize"]  = self::orderSettleMoneyCalc( $orderId, $con4 );
        //其他成本
        $con5[] = ['statement_cate','=','cost'];  //卖家，入账
//        $con4[] = ['change_type','=','1'];
        $data["cost_prize"]  = self::orderSettleMoneyCalc( $orderId, $con5 );
        //毛利
        $data["final_prize"]    = self::orderSettleMoneyCalc( $orderId );
        //更新金额
        OrderService::getInstance($orderId)->update( $data );
//  `pre_prize` decimal(10,2) DEFAULT '0.00' COMMENT '最小定金，关联发车付款进度',
//  `order_prize` decimal(10,2) DEFAULT '0.00' COMMENT '订单金额，关联发车付款进度',
//  `pay_prize` decimal(10,2) DEFAULT '0.00' COMMENT '已收金额',
//  `refund_prize` decimal(10,2) DEFAULT '0.00' COMMENT '收退金额',
//  `outcome_prize` decimal(10,2) DEFAULT '0.00' COMMENT '已付金额',
//  `outcome_refund_prize` decimal(10,2) DEFAULT '0.00' COMMENT '付退金额',
//  `distri_prize` decimal(10,2) DEFAULT '0.00' COMMENT '已分派金额',
    }
    
    /*
     * 订单是否已对账
     * TODO 优化 一笔订单在一个客户下只对账一次。
     */
    public static function hasStatement( $customerId, $orderId )
    {
        $con[] = ['customer_id','=',$customerId];
        $con[] = ['order_id','=',$orderId];
        return self::mainModel()->where( $con )->value('id');
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

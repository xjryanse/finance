<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
use xjryanse\order\service\OrderService;
use xjryanse\system\service\SystemCateService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\logic\UserPayLogic;
use think\Db;
use Exception;
/**
 * 收款单-订单关联
 */
class FinanceStatementService {
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStatement';

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
        self::checkTransaction();
        if(!$orderId){
            throw new Exception('订单id必须');
        }
        $con[] = ['order_id','=',$orderId];
        $con[] = ['has_settle','=',0];      //未结算
        $lists = self::mainModel()->where( $con )->select();
        foreach( $lists as $k=>$v){
            //设为未对账
            self::getInstance( $v['id'])->update(['has_confirm'=>0]);
            //然后才能删
            self::getInstance( $v['id'])->delete();
        }
    }
    
    /**
     * 根据订单明细id，生成账单
     * @param type $statementOrderIds   账单订单表的id
     * @return type
     */
    public static function statementGenerate($statementOrderIds = [])
    {
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
        $cateKey            = $statementType;
        $keyId              = SystemCateService::keyGetId('prizeKeyAll', $cateKey);      //prizeKeyAll，全部的价格key
        if($keyId){
            //处理对账单名称
            $statementName .= $fGoodsName ." ". SystemCateService::getInstance($keyId)->fCateName();
        } else {
            $statementName .= $fGoodsName;
        }
        return $statementName;
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
    
    public static function save( $data )
    {
        self::checkTransaction();
        //无单条订单，无订单数组
        if(!Arrays::value($data, 'order_id') && !Arrays::value($data, 'orders') && !Arrays::value($data, 'statementOrderIds')){
            throw new Exception('请选择订单');
        }
        //转为数组存
        if(is_string($data['order_id']) && $data['order_id']){
            $cateKey            = Arrays::value($data, 'statement_type');
            $data['statement_name'] = self::getStatementNameByOrderId( $data['order_id'] , $cateKey);
        }
        $res = self::commSave($data);
        //转为数组存
        if(is_string($data['order_id'])){
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
            $data['belong_cate']        = Arrays::value($statementOrderInfo, 'belong_cate');
            $data['statement_cate']     = Arrays::value($statementOrderInfo, 'statement_cate');
            $data['user_id']            = Arrays::value($statementOrderInfo, 'user_id');
            $data['manage_account_id']  = Arrays::value($statementOrderInfo, 'manage_account_id');
            $data['statement_name']     = Arrays::value($statementOrderInfo, 'statement_name');
            $data['busier_id']          = Arrays::value($statementOrderInfo, 'busier_id');
            if($statementOrderIdCount == 1){
                $data['order_id']       = Arrays::value($statementOrderInfo, 'order_id');
            }
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
        $hasSettle      = Arrays::value($data, 'has_settle');
        $accountLogId   = Arrays::value($data, 'account_log_id');
        if( $hasSettle ){
            self::getInstance($uuid)->settle( $accountLogId );
        } else {
            self::getInstance($uuid)->cancelSettle();
        }
    }
    /*
     * 更新商品名称
     */
    public static function extraAfterSave(&$data, $uuid) {
        $goodsName = FinanceStatementOrderService::statementGoodsName($uuid);
        return self::mainModel()->where('id',$uuid)->update(['goods_name'=>$goodsName]);
    }
    
    public static function extraAfterUpdate(&$data, $uuid) {
        if(isset($data['has_confirm'])){
            $hasConfirm = Arrays::value($data, 'has_confirm');
            $con[] = ['statement_id','=',$uuid ];
            $lists = FinanceStatementOrderService::lists( $con );
            foreach( $lists as $key=>$value){
                FinanceStatementOrderService::getInstance( $value['id'] )->update( [ 'has_confirm' => $hasConfirm ] );
            }
        }
        //更新商品名称
        $goodsName = FinanceStatementOrderService::statementGoodsName($uuid);
        return self::mainModel()->where('id',$uuid)->update(['goods_name'=>$goodsName]);
    }

    /**
     * 对冲结算逻辑
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
        //冗余
        $con[] = ['statement_id','=',$this->uuid];
        $lists = FinanceStatementOrderService::lists( $con );
        foreach( $lists as $v){
            FinanceStatementOrderService::getInstance( $v['id'] )->update(['has_settle'=>1]);   //更新为已结算
        }
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
        //冗余
        FinanceStatementOrderService::mainModel()->where('statement_id',$this->uuid)->update(['has_settle'=>0]);   //更新为未结算
        
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
     * 退款账单自动关联一笔付款账单
     */
    public function refUni()
    {
        $info = $this->get();
        Debug::debug('退款账单信息',$info);        
        if($info['ref_statement_id']){
            return false;
        }
        if(!$info['order_id']){
            throw new Exception('仅支持单订单账单');
        }
        //剩余金额大于等于当前账单金额，且金额从小到大排列，取第一条记录
        $con[] = ['order_id','=',$info['order_id']];
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

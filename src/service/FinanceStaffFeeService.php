<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\goods\service\GoodsService;
use xjryanse\order\service\OrderService;
use xjryanse\user\service\UserService;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Debug;
use Exception;
/**
 * 
 */
class FinanceStaffFeeService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFee';
    //直接执行后续触发动作
    protected static $directAfter = true;
    
    ///从ObjectAttrTrait中来
    // 定义对象的属性
    protected $objAttrs = [];
    // 定义对象是否查询过的属性
    protected $hasObjAttrQuery = [];
    // 定义对象属性的配置数组
    protected static $objAttrConf = [
        'financeStaffFeeList'=>[
            'class'     =>'\\xjryanse\\finance\\service\\FinanceStaffFeeListService',
            'keyField'  =>'fee_id',
            'master'    =>true
        ]
    ];
    
    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        throw new Exception(__CLASS__.__FUNCTION__.'方法弃用');
    }

    /**
     * 钩子-保存后
     */
    public static function extraAfterSave(&$data, $uuid) {
        
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        throw new Exception(__CLASS__.__FUNCTION__.'方法弃用');
    }

    /**
     * 钩子-更新后
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete() {
        throw new Exception(__CLASS__.__FUNCTION__.'方法弃用');
        self::checkTransaction();
        if($this->uuid){
            $con[] = ['fee_id','=',$this->uuid];
            FinanceStaffFeeListService::mainModel()->where($con)->delete();
        }
    }
    /**
     * 20220623生成订单
     */
    public function generateOrderGetId(){
        $info = $this->get();
        if(Arrays::value($info,'order_id')){
            return $info['order_id'];
        }
        //部门
        $data['dept_id']        = $info['dept_id'];
        //报销人
        $data['seller_user_id'] = $info['user_id'];
        //20220623 TODO 兼容报错
        $data['user_id']        = $info['user_id'];
        //报销时间
        $data['plan_start_time'] = $info['apply_time'];
        
        $saleType           = ORDER_TYPE_STAFF_FEE;
        $goodsInfo          = GoodsService::getBySaleType($saleType);
        $data['order_type'] = $saleType;    //订单类型默认包车
        $goodsArr[] = ['goods_id'=>$goodsInfo['id'], 'amount'=>1];
        $orderId = OrderService::orderRam($goodsArr, $data);
        $this->updateRam(['order_id'=>$orderId]);
        
        return $orderId;
    }
    /**
     * 20220623，取消订单id
     */
    public function cancelOrder(){
        $this->updateRam(['order_id'=>'']);
    }
    
    public static function ramPreSave(&$data, $uuid) {
        $userId     = Arrays::value($data,'user_id');
        $userInfo   = UserService::getInstance($userId)->get();
        // 获取用户所属的部门
        $data['dept_id'] = isset($data['dept_id']) ? $data['dept_id'] : $userInfo['dept_id'];
    }
    /**
     * 生成
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterSave(&$data, $uuid) {
        self::getInstance($uuid)->generateOrderGetId();
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
        if($info['order_id']){
            OrderService::getInstance($info['order_id'])->objAttrsUpdate('financeStaffFee',$uuid, $info);
            OrderService::getInstance($info['order_id'])->updateRam(['status'=>1]);
        }
    }
    
    public function ramPreDelete(){
        $info = $this->get();
        if($info['has_settle']){
            throw new Exception('已支付不可删');
        }
        if($this->uuid){
            $con[] = ['fee_id','=',$this->uuid];
            $ids = FinanceStaffFeeListService::mainModel()->where($con)->column('id');
            foreach($ids as $id){
                FinanceStaffFeeListService::getInstance($id)->deleteRam();
            }
        }
        //20220624关联删订单
        if($info['order_id']){
            OrderService::getInstance($info['order_id'])->deleteRam();
        }
    }
    
    /**
     * 钩子-删除后
     */
    public function extraAfterDelete() {
        
    }
    /**
     * 更新费用金额
     * 一般用于写入明细后更新总额
     */
    public function feeMoneyUpdate(){
        $con[] = ['fee_id','=',$this->uuid];
        $money = FinanceStaffFeeListService::mainModel()->where($con)->sum('money');
        $data['money'] = $money;
        return $this->update($data);
    }
    
    public function feeMoneyUpdateRam(){
        $data['money'] = $this->calFeeMoney();
        return $this->updateRam($data);
    }
    /**
     * 20220623:计算佣金总额
     */
    public function calFeeMoney(){
        $lists = $this->objAttrsList('financeStaffFeeList');
        return array_sum(array_column($lists,'money'));
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

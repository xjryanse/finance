<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\approval\interfaces\ApprovalOutInterface;
use xjryanse\approval\service\ApprovalThingService;
use xjryanse\user\service\UserService;
use xjryanse\universal\service\UniversalItemFormService;
use xjryanse\finance\service\FinanceStaffFeeGroupTypeService;
use xjryanse\finance\service\FinanceStaffFeeTypeService;
// use xjryanse\bus\service\BusFixService;
// use xjryanse\bus\service\BusOilingService;
use xjryanse\system\service\SystemColumnListService;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\DbOperate;
use xjryanse\bus\service\BusService;
use think\Db;
use Exception;

/**
 * 
 */
class FinanceStaffFeeService implements MainModelInterface, ApprovalOutInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;
    use \xjryanse\approval\traits\ApprovalOutTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFee';
    //直接执行后续触发动作
    protected static $directAfter = true;
    
    use \xjryanse\finance\service\staffFee\FieldTraits;
    use \xjryanse\finance\service\staffFee\DoTraits;
    use \xjryanse\finance\service\staffFee\TriggerTraits;
    use \xjryanse\finance\service\staffFee\ListTraits;
    use \xjryanse\finance\service\staffFee\PaginateTraits;

    /**
     * 20220623，取消订单id
     */
    public function cancelOrder() {
        self::stopUse(__METHOD__);
        // $this->updateRam(['order_id' => '']);
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
        
        if(FinanceStatementOrderService::belongTableHasStatementOrder($belongTable,$this->uuid, $prizeKey)){
            throw new Exception('账单明细已存在');
        }
        
        $res = FinanceStatementOrderService::belongTablePrizeKeySaveRam($prizeKey, $info['money'], $belongTable, $this->uuid, $data);
        return $res;
    }
    
    /**
     * 20320726:订单账单添加
     */
    public function addStatementBatch($ids) {
        // 生成一个付款账单，多单报销合并一单付款
        $con    = [];
        $con[]  = ['id','in',$ids];
//        $lists  = self::where($con)->select();
//        $arr    = $lists ? $lists->toArray() : [];
        
        $tableSql = self::staffFeeSqlWithOtherTable();
        $arr = Db::table($tableSql)->where($con)->select();
        
        
        $userIds = array_unique(array_column($arr,'user_id'));
        if(count($userIds) > 1){
            throw new Exception('请选择同一申请人的报销单');
        }
        
        $prizeKey       = 'staffFee';
        // $belongTable    = self::getTable();
        // 初始化报销单据
        foreach($arr as $v){
            $belongTable = $v['sourceTable'];
            if(!FinanceStatementOrderService::belongTableHasStatementOrder($belongTable,$v['id'], $prizeKey)){
                $service = DbOperate::getService($belongTable);
                $service::getInstance($v['id'])->addStatementOrder();
            }
        }
        // 20231113:提交处理
        DbOperate::dealGlobal();

        $ids = array_unique(array_column($arr,'id'));
        // $statementOrderIds  = FinanceStatementOrderService::belongTableStatementOrderIds($belongTable, $ids);
        $statementOrderIds  = FinanceStatementOrderService::belongTableIdStatementOrderIds($ids);
        $statementInfo      = FinanceStatementOrderService::statementGenerateRam($statementOrderIds);

        return $statementInfo;
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
                        $v['feeArr'] = array_column($feeArr, 'money', 'fee_type_id');
                        // 20230418：明细数
                        $v['feeListCount'] = count($feeArr);
                        $v['feeListSum'] = array_sum(array_column($feeArr, 'money'));
                        // 是否本人的报销单
                        $v['userIsMe'] = $v['user_id'] == session(SESSION_USER_ID) ? 1:0;
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
     * 入参默认带
     */
    public static function findFeePreData($data){
        if(Arrays::value($data, 'id')){
            $data = self::getInstance($data['id'])->get();
            $data['feeArr'] = FinanceStaffFeeListService::calFeeIdArr($data['id']);
            // dump($data);
        } else {
            $data['user_id']    = session(SESSION_USER_ID);
            $data['apply_time'] = date('Y-m-d H:i:s');            
            $data['status']     = 1;
        }

        if(!Arrays::value($data,'bus_id')){
            $con    = [];
            $con[]  = ['current_driver','=',session(SESSION_USER_ID)];
            $data['bus_id'] = BusService::mainModel()->where($con)->value('id');
        }
        
        $feeGroup   = Arrays::value($data,'fee_group');
        //items = 
        $itemIds    = FinanceStaffFeeGroupTypeService::dimTypeIdsByFeeGroup($feeGroup);
        
        $conI = [];
        $conI[]     = ['id','in',$itemIds];
        $conI[]     = ['status','=',1];
        $items      = FinanceStaffFeeTypeService::lists($conI);
        $fieldsArr  = [];
        foreach($items as $v){
            $option = $v['option'] ? SystemColumnListService::getOption($v['type'], $v['option']) : [];
            $fieldsArr[] = ['label'=>$v['fee_name'],'field'=>$v['id'],'type'=>$v['type'],'option'=>$option];
        }
        $rFields = UniversalItemFormService::dynArrFields($fieldsArr);
        // $data['uniDynArr'] = [];
        $data['uniDynArr']['feeArr'] = $rFields;
        
        return $data;
    }
    
    /**
     * 订单是否有数据
     */
    public static function orderHasData($orderId, $subId = ''){
        $con    = [];
        $con[]  = ['order_id','=',$orderId];
        if($subId){
            $con[]  = ['sub_id','=',$subId];
        }
        return self::where($con)->count() ? 1: 0;
    }
    /**
     * 订单查询列表
     * @param type $param
     * @return type
     * @throws Exception
     */
    public static function listByOrder($param){
        $orderId = Arrays::value($param, 'order_id');
        if(!$orderId){
            throw new Exception('订单号必须');
        }

        $con    = [];
        $con[]  = ['order_id','=',$orderId];
        $subId  = Arrays::value($param, 'sub_id');
        if($subId){
            $con[]  = ['sub_id','=',$subId];
        }
        
        $arrObj = self::lists($con);
        return $arrObj ? $arrObj->toArray() : [];
    }
    
    /**
     * 执行获取打包单据前数据
     * /admin/finance/find?admKey=companyUser&findMethod=findPackPreGet
     * @return type
     */
    protected static function packPreGet($ids) {
        if(!$ids){
            return [];
        }
        $con[]      = ['id', 'in', $ids];
        $tableSql = self::staffFeeSqlWithOtherTable();
        $listsArr = Db::table($tableSql)->where($con)->select();

        // $lists      = self::where($con)->select();
        // $listsArr   = $lists ? $lists->toArray() : [];
        $userIds    = Arrays2d::uniqueColumn($listsArr, 'user_id');
        if(count($userIds) >1){
            throw new Exception('请选择同一用户报销单');
        }

        $money              = Db::table($tableSql)->where($con)->sum('money');
        $data['user_id']    = $userIds[0];
        $data['number']     = count($listsArr);
        $data['money']      = $money;
        $data['id']         = $ids;
        $data['status']     = 1;

        return $data;
    }
    
    /**
     * 带其他表的报销
     */
    private static function staffFeeSqlWithOtherTable(){
        // 只提取管理员
        $sqlStaffFee = self::mainModel()->sqlBaoFinanceStaffFee();
        // 维修表
        // $sqlFix = BusFixService::mainModel()->sqlBaoFinanceStaffFee();
        // 加油表（20240326发现驾驶员重复填写调整）
        // $sqlOil = BusOilingService::mainModel()->sqlBaoFinanceStaffFee();

        $tableAll = '('.$sqlStaffFee.')';
        
        $finalTable = '(select aa.*,ifnull( bb.has_statement, 0 ) AS `hasStatement`,
		ifnull( bb.has_settle, 0 ) AS `hasSettle` from '.$tableAll .' as aa'
                .' left join w_finance_statement_order as bb'
                . ' ON aa.id = bb.belong_table_id) as mainTable';

        return $finalTable;
    }

}

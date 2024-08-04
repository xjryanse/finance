<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use think\Db;
use Exception;

/**
 * 账户流水表
 */
class FinanceAccountLogService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\StaticsModelTrait;
    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceAccountLog';
    //直接执行后续触发动作
    protected static $directAfter = true;
    // 20230710：开启方法调用统计
    protected static $callStatics = true;

    use \xjryanse\finance\service\accountLog\FieldTraits;
    use \xjryanse\finance\service\accountLog\ListTraits;
    use \xjryanse\finance\service\accountLog\TriggerTraits;
    use \xjryanse\finance\service\accountLog\PaginateTraits;

    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {
            // $logArr = WechatWePubTemplateMsgLogService::groupBatchCount('from_table_id', $ids);
            $manageAccountLogCount = FinanceManageAccountLogService::groupBatchCount('from_table_id', $ids);            
            foreach ($lists as &$v) {
                //模板消息数
                // $v['wechatWePubTemplateMsgLogCount'] = Arrays::value($logArr, $v['id'], 0);
                // 20230726 冲账记录数:0未冲账；1已冲账
                $v['manageLogCount']  = Arrays::value($manageAccountLogCount, $v['id'], 0);
            }
            return $lists;
        },true);
    }

    public function delete() {
        $info = $this->get();
        if (Arrays::value($info, 'statement_id')) {
            $statementId = Arrays::value($info, 'statement_id');
            $financeStatement = FinanceStatementService::getInstance($statementId)->get(0);
            if (Arrays::value($financeStatement, 'has_settle')) {
                throw new Exception('关联账单已入账不可操作');
            }
        }
        //来源表有记录，则报错
        if ($info['from_table'] && $info['from_table_id']) {
            $conc = [['from_table','=',$info['from_table']],['from_table_id','=',$info['from_table_id']]];
            $count = self::where($conc)->count();
            // 20240727:如果不止一条，可能是程序写错了，后期核查需要删除
            if ($count == 1 && Db::table($info['from_table'])->where('id', $info['from_table_id'])->find()) {
                throw new Exception('请先删除' . $info['from_table'] . '表,id为' . $info['from_table_id'] . '的记录');
            }
        }

        $res = $this->commDelete();
        //更新账户余额
        FinanceAccountService::getInstance($info['account_id'])->updateRemainMoney();
        //删除管理账的明细
        $con[] = ['from_table', '=', self::mainModel()->getTable()];
        $con[] = ['from_table_id', '=', $info['id']];
        $lists = FinanceManageAccountLogService::lists($con);
        foreach ($lists as &$v) {
            //一个个删，可能有关联
            FinanceManageAccountLogService::getInstance($v['id'])->delete();
        }

        return $res;
    }

    /**
     * 对账单是否有收款记录
     * @param type $statementId
     * @return type
     */
    public static function statementHasLog($statementId) {
        $logs = FinanceStatementService::getInstance($statementId)->objAttrsList('financeAccountLog');
        return count($logs);
//        $con[] = ['statement_id','=',$statementId];
//        return self::count($con) ? self::find( $con ) : false;
    }

    /**
     * 20220527
     * 账单id取账户类型
     */
    public static function statementIdsGetAccountType($statementId) {
        //20220618:空账单不用查
        if (!$statementId || (is_array($statementId) && count($statementId) == 1 && !$statementId[0])) {
            return '';
        }
        $statementIds = is_array($statementId) ? $statementId : [$statementId];
        $accountLogIds = [];
        foreach ($statementIds as $stId) {
            $statementInfo = FinanceStatementService::getInstance($stId)->get();
            $accountLogIds[] = $statementInfo['account_log_id'];
        }
        $accountIds = [];
        foreach ($accountLogIds as $logId) {
            $logInfo = $logId ? self::getInstance($logId)->get() : [];
            $accountIds[] = Arrays::value($logInfo, 'account_id');
        }

//
//        //statementId,取accountLog表的accountId
//        $con1[] = ['statement_id','in',$statementId];
//        $accountIds = self::mainModel()->where($con1)->column('distinct account_id');
        //accountId,取类型
        //$con2[] = ['id','in',$accountIds];
        //$accountTypes = FinanceAccountService::mainModel()->where($con2)->column('distinct account_type');
        $accountTypes = FinanceAccountService::columnAccountTypes($accountIds);

        return $accountTypes ? (count($accountTypes) > 1 ? 'mix' : $accountTypes[0] ) : '';
    }

    /**
     * 账单已完结金额;
     * 适用于组合支付中查询金额进行处理
     */
    public static function statementFinishMoney($statementId) {
        $logs = FinanceStatementService::getInstance($statementId)->objAttrsList('financeAccountLog');
        return array_sum(array_column($logs, 'money'));

//        $con[] = ['statement_id','=',$statementId];
//        return self::sum($con,'money');
    }

    /**
     * 来源表和来源id查是否有记录：
     * 一般用于判断该笔记录是否已入账，避免重复入账
     * @param type $fromTable   来源表
     * @param type $fromTableId 来源表id
     */
    public static function hasLog($fromTable, $fromTableId) {
        //`from_table` varchar(255) DEFAULT '' COMMENT '来源表',
        //`from_table_id` varchar(32) DEFAULT '' COMMENT '来源表id',
        $con[] = ['from_table', '=', $fromTable];
        $con[] = ['from_table_id', '=', $fromTableId];

        return self::count($con) ? true : false;
    }

    /**
     * 计算客户端的账户余额
     * @param type $customerId  公司id
     * @param type $accountId   账户id
     */
    public static function customerMoneyCalc($customerId, $accountId) {
        $con[] = ['customer_id', '=', $customerId];
        $con[] = ['account_id', '=', $accountId];
        return self::mainModel()->where($con)->sum('money');
    }

    /**
     * 20230116
     * 数据统计字段
     */
    protected static function staticsFields() {
        $fields[] = "dept_id";
        $fields[] = "account_id";
        $fields[] = "sum( money ) AS `allMoney`";
        $fields[] = "sum( IF ( ( `change_type` = 1 ), `money`, 0 ) ) AS `incomeMoney`";
        $fields[] = "sum( IF ( ( `change_type` = 1 ), 1, 0 ) ) AS `incomeCount`";
        $fields[] = "sum( IF ( ( `change_type` = 2 ), `money`, 0 ) ) AS `outcomeMoney`";
        $fields[] = "sum( IF ( ( `change_type` = 2 ), 1, 0 ) ) AS `outcomeCount`";
        return $fields;
    }

    /**
     * 动态枚举数组
     * @return string
     */
    protected static function staticsDynArr() {
        $tableName = FinanceAccountService::getTable();
        $dynArrs['account_id'] = 'table_name=' . $tableName . '&key=id&value=account_name';
        return $dynArrs;
    }

    public static function monthlyStatics($yearmonth, $moneyType) {
        $moneyTypeN = $moneyType ?: ['allMoney'];
        $dynArrs = self::staticsDynArr();
        $groupFields = ['account_id', 'dept_id'];
        return self::staticsMonthly($yearmonth, $moneyTypeN, 'bill_time', $groupFields, 'moneyType', $dynArrs);
    }

    public static function yearlyStatics($year, $moneyType) {
        $moneyTypeN = $moneyType ?: ['allMoney'];
        $dynArrs = self::staticsDynArr();
        $groupFields = ['account_id', 'dept_id'];
        return self::staticsYearly($year, $moneyTypeN, 'bill_time', $groupFields, 'moneyType', $dynArrs);
    }
    /**
     * 20240303
     */
    public function doOrderTypeUpdate(){
        $data['statement_id'] = $this->fStatementId();
        return $this->updateRam($data);
    }
}

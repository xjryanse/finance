<?php

namespace xjryanse\finance\service;

use think\Db;
use Exception;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Sql;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
use xjryanse\system\service\SystemCompanyService;

/**
 * 账户表
 */
class FinanceAccountService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\StaticModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceAccount';
    //直接执行后续触发动作
    protected static $directAfter = true;

    use \xjryanse\finance\service\account\FieldTraits;

    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {
                    $arrs   = FinanceAccountLogService::staticsAll('account_id');
                    $arrObj = Arrays2d::fieldSetKey($arrs, 'account_id');
                    foreach ($lists as &$v) {
                        $v['allMoney']      = Arrays::value($arrObj, $v['id']) ? $arrObj[$v['id']]['allMoney'] : 0;
                        $v['allCount']      = Arrays::value($arrObj, $v['id']) ? $arrObj[$v['id']]['num'] : 0;
                        $v['incomeMoney']   = Arrays::value($arrObj, $v['id']) ? $arrObj[$v['id']]['incomeMoney'] : 0;
                        $v['incomeCount']   = Arrays::value($arrObj, $v['id']) ? $arrObj[$v['id']]['incomeCount'] : 0;
                        $v['outcomeMoney']  = Arrays::value($arrObj, $v['id']) ? $arrObj[$v['id']]['outcomeMoney'] : 0;
                        $v['outcomeCount']  = Arrays::value($arrObj, $v['id']) ? $arrObj[$v['id']]['outcomeCount'] : 0;
                    }
                    return $lists;
                });
    }
    
    /*
     * 用户id和账户类型创建，一个类型只能有一个账户
     */

    public static function createAccount($accountType, $data = []) {
        $con[] = ['account_type', '=', $accountType];
        $data['account_type'] = $accountType;
        return self::count($con) ? false : self::save($data);
    }

    /**
     * 根据账户类型取id
     * @param type $companyId   公司id
     * @param type $accountType 账户类型
     * @return type
     */
    public static function getIdByAccountType($companyId, $accountType) {
        $con[] = ['account_type', '=', $accountType];
        $listsAll = SystemCompanyService::getInstance($companyId)->objAttrsList('financeAccount');
        $info = Arrays2d::listFind($listsAll, $con);
        if (!$info) {
            $info = self::createAccount($accountType);
        }
        return $info ? $info['id'] : '';
    }

    public function extraPreDelete() {
        self::checkTransaction();
        $con[] = ['account_id', '=', $this->uuid];
        $res = FinanceAccountLogService::mainModel()->where($con)->count(1);
        if ($res) {
            throw new Exception('该账户有流水，不可删除');
        }
    }

    /**
     * 获取账户类型
     * @param type $ids
     */
    public static function columnAccountTypes($ids) {
        $con[] = ['id', 'in', $ids];
        $lists = self::staticConList($con);
        return array_column($lists, 'account_type');
    }

    /**
     * 入账更新
     */
    public function income($value) {
        self::checkTransaction();
        //账户余额更新
        return self::mainModel()->where('id', $this->uuid)->setInc('money', $value);
    }

    /**
     * 资金出账更新
     */
    public function outcome($value) {
        self::checkTransaction();
        //账户余额更新
        return self::mainModel()->where('id', $this->uuid)->setDec('money', $value);
    }

    /**
     * 更新余额
     */
    public function updateRemainMoney() {
//        $con[] = ['account_id','=',$this->uuid];
//        $money = FinanceAccountLogService::mainModel()->where($con)->sum('money');
//        return self::mainModel()->where('id',$this->uuid)->update(['money'=>$money]);
        $mainTable = self::getTable();
        $mainField = "money";
        $dtlTable = FinanceAccountLogService::getTable();
        $dtlStaticField = "money";
        $dtlUniField = "account_id";
        $dtlCon[] = ['main.id', '=', $this->uuid];
        $sql = Sql::staticUpdate($mainTable, $mainField, $dtlTable, $dtlStaticField, $dtlUniField, $dtlCon);
        Debug::debug('updateRemainMoney的$sql', $sql);
        return Db::query($sql);
    }

    /**
     * 20220622
     * @global array $glSqlQuery
     * @return boolean
     */
    public function updateRemainMoneyRam() {
        global $glSqlQuery;
        $mainTable = self::getTable();
        $mainField = "money";
        $dtlTable = FinanceAccountLogService::getTable();
        $dtlStaticField = "money";
        $dtlUniField = "account_id";
        $dtlCon[] = ['main.id', '=', $this->uuid];
        $sql = Sql::staticUpdate($mainTable, $mainField, $dtlTable, $dtlStaticField, $dtlUniField, $dtlCon);
        //扔一条sql到全局变量，方法执行结束后执行
        $glSqlQuery[] = $sql;
        self::staticCacheClear();
        return true;
    }

    /**
     * 接收消息的id
     */
    public static function acceptMsgIds() {
        $con[] = ['status', '=', 1];
        $con[] = ['accept_msg', '=', 1];
        return self::where($con)->column('id');
    }
    /**
     * 提取手工记账的id列表
     * @describe listForDailyOutcomeList使用
     * 20231115
     */
    public static function calHandleAccountIds($con = []){
        $con[] = ['is_handle','=',1];
        return self::where($con)->column('id');
    }
    

}

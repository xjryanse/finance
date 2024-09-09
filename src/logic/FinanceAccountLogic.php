<?php

namespace xjryanse\finance\logic;

use xjryanse\system\interfaces\AccountLogicInterface;
use xjryanse\finance\service\FinanceAccountService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\logic\Arrays;
use Exception;

/**
 * 账户逻辑
 */
class FinanceAccountLogic implements AccountLogicInterface {

    /**
     * 入账逻辑
     * @param type $companyId      公司id
     * @param type $accountType 账户类型
     * @param type $value       变动值
     * @param type $data        额外数据
     * @return type
     */
    public static function doIncome($companyId, $accountType, $value, $data = []) {
        //事务校验
        FinanceAccountService::checkTransaction();
        $fromTable = isset($data['from_table']) && $data['from_table'] ? $data['from_table'] : '';
        $fromTableId = isset($data['from_table_id']) && $data['from_table_id'] ? $data['from_table_id'] : '';

        if ($fromTable && $fromTableId && FinanceAccountLogService::hasLog($fromTable, $fromTableId)) {
            throw new Exception($fromTable . $fromTableId . '已经入账过了');
        }

        //账户id
        $accountId = FinanceAccountService::getIdByAccountType($companyId, $accountType);

        $info = FinanceAccountService::getInstance($accountId)->get(0);
        //新增流水
        $data['company_id'] = $companyId ?: session(SESSION_COMPANY_ID);
        $data['customer_id'] = Arrays::value($data, 'customer_id');
        $data['user_id'] = Arrays::value($data, 'user_id');
        $data['account_id'] = $info['id'];
        $data['change_type'] = 1;            //进账
        $data['before_money'] = $info['money'];
        $data['money'] = $value;
        $data['current_money'] = $info['money'] + $value;
        $res = FinanceAccountLogService::save($data);

        return $res;
    }

    /*
     * 出账逻辑
     * @param type $companyId      用户id
     * @param type $accountType 账户类型
     * @param type $value       变动值
     * @param type $data        额外数据
     * @param type $permitNegative  是否允许账户余额负值
     * @return type
     */

    public static function doOutcome($companyId, $accountType, $value, $data = [], $permitNegative = false) {
        //事务校验
        FinanceAccountService::checkTransaction();
        //账户id
        $accountId = FinanceAccountService::getIdByAccountType($companyId, $accountType);
        $info = FinanceAccountService::getInstance($accountId)->get(0);

        if (!$permitNegative && $info['money'] - abs($value) < 0) {
            throw new Exception('账户余额不足');
        }
        //新增流水
        $data['company_id'] = $companyId ?: session(SESSION_COMPANY_ID);
        $data['account_id'] = $info['id'];
        $data['change_type'] = 2;    //出账
        $data['before_money'] = $info['money'];
        $data['money'] = -1 * abs($value);
        $data['current_money'] = $info['money'] - abs($value);
        $res = FinanceAccountLogService::save($data);
        return $res;
    }
}

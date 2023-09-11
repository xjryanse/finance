<?php

namespace xjryanse\finance\service;

use xjryanse\user\service\UserService;
use xjryanse\customer\service\CustomerService;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Debug;
use xjryanse\logic\Sql;
use think\Db;
use Exception;

/**
 * 管理账户表
 */
class FinanceManageAccountService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceManageAccount';
    //直接执行后续触发动作
    protected static $directAfter = true;

    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {
                    $logsArr = FinanceManageAccountLogService::groupBatchCount('manage_account_id', $ids);
                    foreach ($lists as &$v) {
                        // 记录登记
                        $v['logCount'] = Arrays::value($logsArr, $v['id']);
                    }
                    return $lists;
                });
    }

    /**
     * 更新余额
     */
    public function updateRemainMoney() {
        $mainTable = self::getTable();
        $mainField = "money";
        $dtlTable = FinanceManageAccountLogService::getTable();
        $dtlStaticField = "money";
        $dtlUniField = "manage_account_id";
        $dtlCon[] = ['main.id', '=', $this->uuid];
        $sql = Sql::staticUpdate($mainTable, $mainField, $dtlTable, $dtlStaticField, $dtlUniField, $dtlCon);
        return Db::query($sql);
    }

    public static function addManageAccountData(&$data) {
        //步骤2
        $customerId = Arrays::value($data, 'customer_id');
        $userId = Arrays::value($data, 'user_id');
        Debug::debug('$customerId', $customerId);
        Debug::debug('$userId', $userId);
        /* 管理账户id */
        if ($customerId) {
            $data['belong_cate'] = 'customer';  //账单归属：单位
            $manageAccountId = self::customerManageAccountId($customerId);
        } else {
            $data['belong_cate'] = 'user';      //账单归属：个人
            $manageAccountId = self::userManageAccountId($userId);
        }
        $data['manage_account_id'] = $manageAccountId;
        return $data;
    }

    /**
     * 20220814:增加
     * @param type $data
     * @param type $uuid
     */
    public static function extraAfterSave(&$data, $uuid) {
        // 20220814
        $userTable = UserService::mainModel()->getTable();
        if (Arrays::value($data, 'belong_table') == $userTable) {
            UserService::getInstance($data['belong_table_id'])->objAttrsPush('financeManageAccount', $data);
        }
        $customerTable = CustomerService::mainModel()->getTable();
        if (Arrays::value($data, 'belong_table') == $customerTable) {
            CustomerService::getInstance($data['belong_table_id'])->objAttrsPush('financeManageAccount', $data);
        }
    }

    /**
     * 
     * @param type $customerId
     * @param type $accountType
     */
    public static function customerManageAccountId($customerId, $accountType = 'customer') {
        $res = CustomerService::getInstance($customerId)->objAttrsList('financeManageAccount');
        $con[] = ['account_type', '=', $accountType];
        $info = Arrays2d::listFind($res, $con);
        if (!$info) {
            $info = self::manageAccountInit(CustomerService::mainModel()->getTable(), $customerId, $accountType);
        }
        return $info ? $info['id'] : '';
        // return self::manageAccountId(CustomerService::mainModel()->getTable(), $customerId, $accountType);
    }

    /**
     * 
     * @param type $userId      
     * @param type $accountType customer:客户挂账
     */
    public static function userManageAccountId($userId, $accountType = 'customer') {
        $res = UserService::getInstance($userId)->objAttrsList('financeManageAccount');
        $con[] = ['account_type', '=', $accountType];
        $info = Arrays2d::listFind($res, $con);
        if (!$info) {
            $info = self::manageAccountInit(UserService::mainModel()->getTable(), $userId, $accountType);
        }
        return $info ? $info['id'] : '';
    }

    /**
     * 管理账户编号
     * 有公司账拿公司账，没公司拿用户
     */
    public static function manageAccountId($customerId, $userId) {
        $manageAccountId = '';
        // 有客户，先拿客户账户
        if ($customerId) {
            $manageAccountId = FinanceManageAccountService::customerManageAccountId($customerId);
        }
        // 没账户，拿个人账户
        if (!$manageAccountId) {
            $manageAccountId = FinanceManageAccountService::userManageAccountId($userId);
        }
        return $manageAccountId;
    }

    /**
     * 账户初始化
     * @param type $belongTable
     * @param type $belongTableId
     * @param type $accountType
     * @return type
     * @throws Exception
     */
    protected static function manageAccountInit($belongTable, $belongTableId, $accountType, $data = []) {
        if (!$belongTableId) {
            throw new Exception('$belongTableId不可空');
        }
        $con[] = ['belong_table', '=', $belongTable];
        $con[] = ['belong_table_id', '=', $belongTableId];
        $con[] = ['account_type', '=', $accountType];
        $info = self::find($con);
        if ($info) {
            return false;
        }

        $data['company_id'] = session(SESSION_COMPANY_ID);
        $data['belong_table'] = $belongTable;
        $data['belong_table_id'] = $belongTableId;
        $data['account_type'] = $accountType;
        return self::save($data);
    }

    /**
     *
     */
    public function fAppId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    public function fMoney() {
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

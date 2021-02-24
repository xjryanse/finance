<?php

namespace xjryanse\finance\service;

use xjryanse\user\service\UserService;
use xjryanse\customer\service\CustomerService;

/**
 * 管理账户表
 */
class FinanceManageAccountService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceManageAccount';
    
    /**
     * 
     * @param type $customerId
     * @param type $accountType
     */
    public static function customerManageAccountId( $customerId, $accountType='customer' )
    {
        return self::manageAccountId(CustomerService::mainModel()->getTable(), $customerId, $accountType);
    }

    /**
     * 
     * @param type $userId      
     * @param type $accountType customer:客户挂账
     */
    public static function userManageAccountId( $userId, $accountType='customer')
    {
        return self::manageAccountId(UserService::mainModel()->getTable(), $userId, $accountType);
    }

    protected static function manageAccountId($belongTable,$belongTableId,$accountType)
    {
        $con[] = ['belong_table','=',$belongTable];
        $con[] = ['belong_table_id','=',$belongTableId];
        $con[] = ['account_type','=',$accountType];
        $info = self::find( $con );
        if(!$info){
            $data['belong_table']     = $belongTable;
            $data['belong_table_id']  = $belongTableId;
            $data['account_type']   = $accountType;
            $info = self::save($data);
        }
        return $info ? $info['id'] : '';
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

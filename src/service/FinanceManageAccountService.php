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
    

}

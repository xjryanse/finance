<?php

namespace xjryanse\finance\service;

use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
/**
 * 管理账户流水表
 */
class FinanceManageAccountLogService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceManageAccountLog';
    
    public static function extraPreSave(&$data, $uuid) {
        DataCheck::must($data, ['money','manage_account_id']);
        $manageAccountId    = Arrays::value($data, 'manage_account_id');
        $manageAccountInfo  = FinanceManageAccountService::getInstance($manageAccountId)->get();
        $data['belong_table'] = Arrays::value($manageAccountInfo, 'belong_table');
        $data['belong_table_id'] = Arrays::value($manageAccountInfo, 'belong_table_id');

        //出账，负值
        if( Arrays::value($data, 'change_type') == '2' ){
            $data['money']  = -1 * abs($data['money']);
        }
        
        return $data;
    }
    
    public static function extraAfterSave(&$data, $uuid) {
        $manageAccountId    = Arrays::value($data, 'manage_account_id');         
        $customerMoney      = self::moneyCalc( $manageAccountId );
        FinanceManageAccountService::mainModel()->where('id',$manageAccountId)->update(['money'=>$customerMoney]);        
    }    
    
    /**
     * 计算客户端的账户余额
     * @param type $customerId  公司id
     * @param type $accountId   账户id
     */
    public static function moneyCalc( $manageAccountId )
    {
        $con[] = ['manage_account_id','=',$manageAccountId];
        return self::mainModel()->where($con)->sum( 'money' );
    }    
}

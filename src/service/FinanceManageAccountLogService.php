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
    //直接执行后续触发动作
    protected static $directAfter = true;    
    
    public static function extraPreSave(&$data, $uuid) {
        DataCheck::must($data, ['manage_account_id']);  //money可能存在为0的情况
        $accountId          = Arrays::value($data, 'account_id');       //账户id
        $manageAccountId    = Arrays::value($data, 'manage_account_id');
        $manageAccountInfo  = FinanceManageAccountService::getInstance($manageAccountId)->get();
        $data['belong_table'] = Arrays::value($manageAccountInfo, 'belong_table');
        $data['belong_table_id'] = Arrays::value($manageAccountInfo, 'belong_table_id');

        //出账，负值
        if( Arrays::value($data, 'change_type') == '2' ){
            $data['money']  = -1 * abs($data['money']);
        }
        //入账，正值
        if( Arrays::value($data, 'change_type') == '1' ){
            $data['money']  = abs($data['money']);
        }
        //账户id，取账户类型
        if($accountId){
            $data['account_type'] = FinanceAccountService::getInstance( $accountId )->fAccountType();
        }
        
        return $data;
    }
    
    public static function extraAfterSave(&$data, $uuid) {
        $manageAccountId    = Arrays::value($data, 'manage_account_id');         
        $customerMoney      = self::moneyCalc( $manageAccountId );
        FinanceManageAccountService::mainModel()->where('id',$manageAccountId)->update(['money'=>$customerMoney]);        
    }
    /**
     * 
     * @param type $data
     * @param type $uuid
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        $manageAccountId    = Arrays::value($info, 'manage_account_id');
        $customerMoney      = self::moneyCalc( $manageAccountId );
        FinanceManageAccountService::mainModel()->where('id',$manageAccountId)->update(['money'=>$customerMoney]);        
    }
    
    public function delete()
    {
        $info = $this->get();
        $res = $this->commDelete();
        $manageAccountId    = Arrays::value($info, 'manage_account_id');         
        $customerMoney      = self::moneyCalc( $manageAccountId );
        FinanceManageAccountService::mainModel()->where('id',$manageAccountId)->update(['money'=>$customerMoney]);        

        return $res;
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
    
    /**
     * 来源表和来源id查是否有记录：
     * 一般用于判断该笔记录是否已入账，避免重复入账
     * @param type $fromTable   来源表
     * @param type $fromTableId 来源表id
     */
    public static function hasLog( $fromTable, $fromTableId )
    {
        //`from_table` varchar(255) DEFAULT '' COMMENT '来源表',
        //`from_table_id` varchar(32) DEFAULT '' COMMENT '来源表id',
        $con[] = ['from_table','=',$fromTable];
        $con[] = ['from_table_id','=',$fromTableId];
        
        return self::count($con) ? self::find( $con ) : false;
    }    
}

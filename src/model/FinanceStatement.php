<?php
namespace xjryanse\finance\model;

/**
 * 账户收款单表
 */
class FinanceStatement extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        [
            'field'     =>'user_id',
            'uni_name'  =>'user',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => true,
        ],
        [
            'field'     =>'dept_id',
            'uni_name'  =>'system_company_dept',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => true,
        ],
        [
            'field'     =>'order_id',
            'uni_name'  =>'order',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],
        [
            'field'     =>'customer_id',
            'uni_name'  =>'customer',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],
        [
            'field'     =>'account_log_id',
            'uni_name'  =>'finance_account_log',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],
        // 20230726：特殊能行？
        [
            'field'     =>'id',
            'uni_name'  =>'finance_manage_account_log',
            'uni_field' =>'from_table_id',
            'exist_field'=>'isManageAccountLogExist',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],
    ];

    
        /**
     * 用户头像图标
     * @param type $value
     * @return type
     */
    public function getFileIdAttr($value) {
        return self::getImgVal($value);
    }

    /**
     * 图片修改器，图片带id只取id
     * @param type $value
     * @throws \Exception
     */
    public function setFileIdAttr($value) {
        return self::setImgVal($value);
    }
    
    public function setStartTimeAttr($value) {
        return self::setTimeVal($value);
    }

    public function setEndTimeAttr($value) {
        return self::setTimeVal($value);
    }    
}
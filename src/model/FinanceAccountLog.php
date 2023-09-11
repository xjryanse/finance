<?php
namespace xjryanse\finance\model;

/**
 * 账户流水表
 */
class FinanceAccountLog extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        [
            'field'     =>'account_id',
            // 去除prefix的表名
            'uni_name'  =>'finance_account',
            'uni_field' =>'id',
            'del_check' => true,
        ],
        [
            'field'     =>'user_id',
            // 去除prefix的表名
            'uni_name'  =>'user',
            'uni_field' =>'id',
            'del_check' => true,
        ],
        [
            'field'     =>'statement_id',
            // 去除prefix的表名
            'uni_name'  =>'finance_statement',
            'uni_field' =>'id',
            'del_check' => true,
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
    
    public function setBillTimeAttr($value) {
        return self::setTimeVal($value);
    }
    
    public function setFileIdAttr($value) {
        return self::setImgVal($value);
    }
    public function getFileIdAttr($value) {
        return self::getImgVal($value,true);
    }
}
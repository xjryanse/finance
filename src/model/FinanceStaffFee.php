<?php
namespace xjryanse\finance\model;

/**
 * 
 */
class FinanceStaffFee extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        //性能不佳
        [
            'field'     =>'user_id',
            'uni_name'  =>'user',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => true,
        ],[
            'field'     =>'approval_thing_id',
            'uni_name'  =>'approval_thing',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],[
            'field'     =>'id',
            'uni_name'  =>'finance_statement_order',
            'uni_field' =>'belong_table_id',
            'exist_field'=>'isStatementOrderExist',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],
    ];
    
    public static $picFields = ['annex'];
    /**
     * 附件
     * @param type $value
     * @return type
     */
    public function getAnnexAttr($value) {
        return self::getImgVal($value);
    }

    /**
     * 图片修改器，图片带id只取id
     * @param type $value
     * @throws \Exception
     */
    public function setAnnexAttr($value) {
        return self::setImgVal($value);
    }
}
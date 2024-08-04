<?php
namespace xjryanse\finance\model;

/**
 * 
 */
class FinanceStaffFeeGroupType extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        [
            'field'     =>'fee_type_id',
            'uni_name'  =>'finance_staff_fee_type',
            'uni_field' =>'id',
        ]
    ];
}
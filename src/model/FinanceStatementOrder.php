<?php
namespace xjryanse\finance\model;

/**
 * 对账单关联订单
 */
class FinanceStatementOrder extends Base
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
            'del_check' => false,
        ],
        [
            'field'     =>'statement_id',
            'uni_name'  =>'finance_statement',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'in_exist'  => true,
        ],
        [
            'field'     =>'order_id',
            'uni_name'  =>'order',
            'uni_field' =>'id',
            'in_list'   => true,
            'in_statics'=> true,
            'in_exist'  => true,
        ],
    ];

    // 20230704:order_source_type:
    // 订单来源类型：
    // 
    

}
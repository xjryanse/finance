<?php
namespace xjryanse\finance\model;

use think\Db;
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
            // 20231113:关联映射表
            'reflect_field' => [
                // hasStatement 映射到表finance_statement_order的has_statement
                'hasStatement'  => ['key'=>'has_statement','nullVal'=>0],
                // nullVal,当关联结果是null时的替代值
                'hasSettle'     => ['key'=>'has_settle','nullVal'=>0]
            ],
        ],
        [
            'field'     =>'order_id',
            // 去除prefix的表名
            'uni_name'  =>'order',
            'uni_field' =>'id',
            'del_check'=> true,
        ],
        [
            'field'     =>'sub_id',
            // 去除prefix的表名
            'uni_name'  =>'order_bao_bus',
            'uni_field' =>'id',
            'del_check' => true,
            'del_msg'   => '已有{$count}条报销记录，请先删除才能操作'
        ]
    ];
    
    public static $picFields = ['annex'];
    
    public static $multiPicFields = ['file'];

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
    
    /**
     * 2023-10-10多图
     * @param type $value
     * @return type
     */
    public function getFileAttr($value) {
        return self::getImgVal($value, true);
    }
    
    public function setFileAttr($value) {
        return self::setImgVal($value);
    }
    
    public static function sqlBaoFinanceStaffFee($con = []){
        $table   = self::getTable();
        $field   = [];
        $field[] = 'id';
        $field[] = 'order_id';
        $field[] = 'sub_id as bao_bus_id';
        $field[] = 'user_id';
        $field[] = 'bus_id';
        $field[] = 'apply_time';
        $field[] = 'money';
        $field[] = 'file';
        $field[] = 'annex';
        $field[] = 'has_settle';
        $field[] = "'".$table."' as sourceTable";
        $field[] = 'status';
        $field[] = "'staffFee' as feeCate";
        $field[] = 'creater';
        $field[] = 'create_time';        
        $field[] = 'b.num as financeStaffFeeListCount';

        $sqlDetail  = Db::table('w_finance_staff_fee_list')
                ->field('fee_id,count(1) as num')
                ->group('fee_id')->buildSql();

        $sql = Db::table($table)->alias('a')
                ->join($sqlDetail.' b','a.id = b.fee_id')
                ->where($con)
                ->field(implode(',',$field))
                ->buildSql();
        return $sql;
    }
}
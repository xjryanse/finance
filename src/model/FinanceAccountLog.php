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
            'field'     =>'customer_id',
            'uni_name'  =>'customer',
            'uni_field' =>'id',
            'in_list'   => false,
            // 'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
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
    
    
    
    /*
     * 聚合下钻
     * billCount-流水数
     * billMoney-流水金额
     * yearmonthDate-统计日期
     * incomeCount-入账笔数
     * outcomeCount-出账笔数
     * 
     * @createTime 20231028
     */
    public static function sqlGroupDownForDailyStatics($timeKey, $con = []){
        // 通用统计字段
        $fields     = self::fieldsStatics();
        // 专用统计字段
        $fields[]   = "date_format( `".$timeKey."`, '%Y-%m-%d' ) as yearmonthDate";
        // 聚合字段
        $groups     = ['account_id',"date_format( `".$timeKey."`, '%Y-%m-%d' )"];
        // $groups =   ['account_id'];
        return self::sqlGroupDown($con,$fields,$groups);
    }
    
    /*
     * 聚合下钻
     * billCount-流水数
     * billMoney-流水金额
     * yearmonthDate-统计日期
     * incomeCount-入账笔数
     * outcomeCount-出账笔数
     * 
     * @createTime 20231028
     */
    public static function sqlGroupDownForMonthlyStatics($timeKey, $con = []){
        // 通用统计字段
        $fields     = self::fieldsStatics();
        // 专用统计字段
        $fields[]   = "date_format( `".$timeKey."`, '%Y-%m' ) as yearmonth";
        // 聚合字段
        $groups     = ['account_id',"date_format( `".$timeKey."`, '%Y-%m' )"];
        // $groups =   ['account_id'];
        return self::sqlGroupDown($con,$fields,$groups);
    }
    
    /*
     * 聚合下钻-年
     * billCount-流水数
     * billMoney-流水金额
     * yearmonthDate-统计日期
     * incomeCount-入账笔数
     * outcomeCount-出账笔数
     * 
     * @createTime 20231028
     */
    public static function sqlGroupDownForYearlyStatics($timeKey, $con = []){
        // 通用统计字段
        $fields     = self::fieldsStatics();
        // 专用统计字段
        $fields[]   = "date_format( `".$timeKey."`, '%Y' ) as year";
        // 聚合字段
        $groups     = ['account_id',"date_format( `".$timeKey."`, '%Y' )"];
        // $groups =   ['account_id'];
        return self::sqlGroupDown($con,$fields,$groups);
    }
    /**
     * 统计字段
     * @return string
     */
    protected static function fieldsStatics(){
        $fields =   ['sum(money) as billMoney','count(1) as billCount'
                ,"sum( IF ( ( `change_type` = 1 ), 1, 0 ) ) AS `incomeCount`"
                ,"sum( IF ( ( `change_type` = 2 ), 1, 0 ) ) AS `outcomeCount`"
                ,"sum( IF ( ( `change_type` = 1 ), money, 0 ) ) AS `incomeMoney`"
                ,"sum( IF ( ( `change_type` = 2 ), money, 0 ) ) AS `outcomeMoney`"
            ];
        return $fields;
    }
}
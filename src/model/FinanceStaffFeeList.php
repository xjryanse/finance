<?php
namespace xjryanse\finance\model;

use xjryanse\finance\service\FinanceStaffFeeTypeService;
use xjryanse\logic\DbOperate;
use think\Db;
/**
 * 报销明细
 */
class FinanceStaffFeeList extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        //性能不佳
        [
            'field'     =>'fee_id',
            'uni_name'  =>'finance_staff_fee',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'in_exist'  => true,
            'del_check' => false,
        ],
        [
            'field'     =>'fee_type_id',
            'uni_name'  =>'finance_staff_fee_type',
            'uni_field' =>'id',
        ]
    ];
    
    public static $picFields = ['annex'];

    // 20231019:默认的时间字段，每表最多一个
    public static $timeField = 'apply_time';
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
     * 部门车辆统计列表
     * @return type
     */
    public static function deptBusStaticsListSql($con = []){

        $staffFeeListTable = self::staffFeeListSql();
        
        $arr            = [];
        $arr[]          = ['table_name'=>$staffFeeListTable,'alias'=>'tB'];
        $arr[]          = ['table_name'=>'w_bus','alias'=>'tC','join_type'=>'inner','on'=>'tB.bus_id = tC.id'];

        $fields         = [];
        $fields[]       = 'tB.dept_id';
        $fields[]       = 'tB.bus_id';
        $fields[]       = 'count(1) as dtlCount';
        $fields[]       = 'sum(tB.money) as money';
        // 报销项目
        $items = FinanceStaffFeeTypeService::where()->select();
        foreach($items as &$v){
            $fields[]       = "sum(if(tB.fee_type_id = '".$v['id']."', tB.money, 0)) as m".$v['id'];
        }
//        $fields[]       = "sum(if(tB.fee_type_id = 'fix', tB.money, 0)) as mFix";
//        $fields[]       = "sum(if(tB.fee_type_id = 'oil', tB.money, 0)) as mOil";

        // $groupFields    = ['tB.bus_id','tB.dept_id'];
        $groupFields    = ['tB.bus_id'];
        
        $sql            = DbOperate::generateJoinSql($fields,$arr,$groupFields, $con, 'tC.seats desc');

        return $sql;
    }
    /**
     * 报销明细列表，带其他关联表
     * @return type
     */
    public static function staffFeeListSql(){
        $tables                 = ['w_finance_staff_fee_list','w_bus_fix','w_bus_oiling'];

        $fields['id']           = ['id','id','id'];
        $fields['company_id']   = ['company_id','company_id','company_id'];
        $fields['dept_id']      = ['dept_id','"test"','"test"'];
        $fields['order_id']     = ['order_id','order_id','order_id'];
        $fields['bao_bus_id']   = ['bao_bus_id','bao_bus_id','bao_bus_id'];
        $fields['bus_id']       = ['bus_id','bus_id','bus_id'];
        $fields['user_id']      = ['user_id','payer_id','bus_id'];
        $fields['fee_id']       = ['fee_id','id','id'];


        $feeTypeFix = FinanceStaffFeeTypeService::keyToId('weiXiu');
        $feeTypeOil = FinanceStaffFeeTypeService::keyToId('jiaYou');

        $fields['fee_type_id']  = ['fee_type_id','"'.$feeTypeFix.'"','"'.$feeTypeOil.'"'];
        $fields['apply_time']   = ['apply_time','fix_time','time'];
        $fields['money']        = ['money','prize','prize'];
        $fields['status']       = ['status','status','status'];
        
        // 维修
        $whereArr[1][] = ['pay_by','=','cash'];
        // 加油
        $whereArr[2][] = ['pay_by','=','cash'];
        $sql = DbOperate::generateUnionSql($tables, $fields, $whereArr);

        return $sql;
    }
}
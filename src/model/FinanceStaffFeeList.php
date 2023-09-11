<?php
namespace xjryanse\finance\model;

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
        ]
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
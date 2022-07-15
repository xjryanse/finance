<?php
namespace xjryanse\finance\model;

/**
 * 报销明细
 */
class FinanceStaffFeeList extends Base
{
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
<?php
namespace xjryanse\finance\model;

/**
 * 账户收款单表
 */
class FinanceIncome extends Base
{
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
    /**
     * 入账时间
     * @param type $value
     * @return type
     */
    public function setIntoAccountTimeAttr($value) {
        return self::setTimeVal($value);
    }
}
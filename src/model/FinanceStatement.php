<?php
namespace xjryanse\finance\model;

/**
 * 账户收款单表
 */
class FinanceStatement extends Base
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
    
    public function setStartTimeAttr($value) {
        return self::setTimeVal($value);
    }

    public function setEndTimeAttr($value) {
        return self::setTimeVal($value);
    }    
}
<?php
namespace xjryanse\finance\model;

/**
 * 账户流水表
 */
class FinanceAccountLog extends Base
{
    public function setBillTimeAttr($value) {
        return self::setTimeVal($value);
    }
    
    public function setFileIdAttr($value) {
        return self::setImgVal($value);
    }
    public function getFileIdAttr($value) {
        return self::getImgVal($value,true);
    }
}
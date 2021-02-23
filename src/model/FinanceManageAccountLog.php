<?php
namespace xjryanse\finance\model;

/**
 * 账户流水表
 */
class FinanceManageAccountLog extends Base
{
    public function setBillTimeAttr($value) {
        return self::setTimeVal($value);
    }
}
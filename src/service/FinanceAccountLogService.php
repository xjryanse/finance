<?php
namespace xjryanse\finance\service;

/**
 * 账户流水表
 */
class FinanceAccountLogService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceAccountLog';

}

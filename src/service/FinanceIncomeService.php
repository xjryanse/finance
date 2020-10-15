<?php
namespace xjryanse\finance\service;

/**
 * 收款单
 */
class FinanceIncomeService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceIncome';

}

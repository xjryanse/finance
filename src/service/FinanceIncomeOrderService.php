<?php
namespace xjryanse\finance\service;

/**
 * 收款单-订单关联
 */
class FinanceIncomeOrderService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceIncomeOrder';

}

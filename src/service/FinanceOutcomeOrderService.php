<?php
namespace xjryanse\finance\service;

/**
 * 付款单-订单关联
 */
class FinanceOutcomeOrderService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceOutcomeOrder';

}

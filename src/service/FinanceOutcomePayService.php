<?php
namespace xjryanse\finance\service;

/**
 * 付款记录表：用户支付
 */
class FinanceOutcomePayService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceOutcomePay';

}

<?php
namespace xjryanse\finance\service;

/**
 * 收款记录表：用户支付
 */
class FinanceIncomePayService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceIncomePay';

}

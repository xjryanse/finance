<?php
namespace xjryanse\finance\service;

/**
 * 付款单
 */
class FinanceOutcomeService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceOutcome';

}

<?php
namespace xjryanse\finance\service;

/**
 * 账户表
 */
class FinanceAccountService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\app\\goods\\model\\GoodsOnlineShop';

}

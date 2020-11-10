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
    
    public static function getBySn($sn)
    {
        $con[] = ['income_sn','=',$sn];
        return self::find( $con );
    }

}

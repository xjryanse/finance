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
    /**
     * 创建新的收款单
     */
    public static function newIncome( $data = [],$prefix='FIN')
    {
        $data['id']         = self::mainModel()->newId();
        $data['income_sn']  = $prefix . $data['id'];
        $res = self::save($data);
        //收款单对应的订单信息存储
        if(isset($data['orders'])){
            foreach($data['orders'] as $v){
                $tmp = $v;
                $tmp['income_id'] = $res['id'];
                //保存单条数据，TODO批量
                FinanceIncomeOrderService::save($tmp);
            }
        }

        return $res;
    }
    

}

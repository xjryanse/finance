<?php
namespace xjryanse\finance\service;

use Exception;
/**
 * 收款记录表：用户支付
 */
class FinanceIncomePayService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceIncomePay';
    /**
     * 新的支付记录
     */
    public static function newIncomePay( $incomeId, $money, $data = [])
    {
        $data['income_id']  = $incomeId;
        $data['money']      = $money;
        
        $res = self::save( $data );
        return $res;
    }
    
    public function delete()
    {
        $info = $this->get(0);
        if(!$info){
            throw new Exception('记录不存在');
        }
        //特殊判断
        if($info['income_status'] != XJRYANSE_OP_TODO ){
            throw new Exception('非待收款状态不能操作');
        }

        return self::mainModel()->where('id',$this->uuid)->delete( );
    }
    
    public static function getBySn($sn)
    {
        $con[] = ['income_pay_sn','=',$sn];
        return self::find( $con );
    }
}

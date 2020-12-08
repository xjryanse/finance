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
    
    public static function paySnToIncomeId( $sn )
    {
        return self::where('income_pay_sn',$sn)->value('income_id');
    }
    
    /**
     * 获取订单的支付单号
     */
    public static function incomeGetPaySn( $incomeId )
    {
        //TODO,状态判断
        $con[] = ['income_id','=',$incomeId ]; 
        return self::where( $con )->order('id desc')->value('income_pay_sn');
    }    
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
    
    /**
     * 根据订单id，取收款单id数组
     */
    public static function columnIncomePaySnByIncomeId( $incomeId ,$con = [])
    {
        $con[] = [ 'income_id', 'in', $incomeId ];
        return self::mainModel()->where( $con )->column('income_pay_sn');
    }
}

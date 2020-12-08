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
    /*
     * 获取订单费用
     * @param type $orderId     订单id
     * @param type $status      收款状态，默认已完成
     */
    public static function getOrderMoney( $orderId ,$status = XJRYANSE_OP_FINISH )
    {
        $con[] = ['order_id','=',$orderId ];
        if( $status ){
            $con[] = [ 'income_status', 'in', $status ];
        }
        return self::sum( $con, 'money' );
    }
    
    /*
     * 获取收款单费用
     * @param type $incomeId    收款单id
     * @param type $status      收款状态，默认已完成
     */
    public static function getIncomeMoney( $incomeId ,$status = XJRYANSE_OP_FINISH )
    {
        $con[] = ['income_id','=',$incomeId ];
        if( $status ){
            $con[] = [ 'income_status', 'in', $status ];
        }
        return self::sum( $con, 'money' );
    }
    /**
     * 根据订单id，取收款单id数组
     */
    public static function columnIncomeIdByOrderId( $orderId ,$con = [])
    {
        $con[] = [ 'order_id', 'in', $orderId ];
        return self::mainModel()->where( $con )->column('income_id');
    }
}

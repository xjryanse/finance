<?php

namespace xjryanse\finance\service\statement;

use xjryanse\logic\Arrays;
use Exception;
/**
 * 
 */
trait DoTraits{

    /**
     * 20230918:前端上报已支付
     * @return type
     */
    public function doUplHasFrontPay(){
        $data['has_front_pay'] = 1;
        return $this->updateRam($data);
    }
    
    /**
     * 执行获取打包单据前数据
     * /admin/finance/find?admKey=staffFee&findMethod=findPackPreGet
     * @return type
     */
    public static function findPackPreGet($param){
        $ids = Arrays::value($param, 'id');
        if(!$ids){
            throw new Exception('请选择单据');
        }
        return self::packPreGetForAccountLog($ids);
    }
}

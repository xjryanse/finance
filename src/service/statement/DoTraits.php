<?php

namespace xjryanse\finance\service\statement;

use xjryanse\logic\Arrays;
use Exception;
use xjryanse\wechat\service\WechatWxPayLogService;
use xjryanse\wechat\service\WechatWxPayRefundLogService;

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
    /**
     * 20240906：前端进行微信支付查单，一般用于开发调试
     */
    public function doWxPayQuery(){
        return WechatWxPayLogService::wxPayQuery($this->uuid);
    }
    /**
     * 20240906：前端进行微信退款查单，一般用于开发调试
     * @return type
     */
    public function doWxRefundQuery(){
        return WechatWxPayRefundLogService::wxRefundQuery($this->uuid);
    }
    /**
     * 
     * @return type
     */
    public function doWxRefundQueryByPayStatement(){
        return WechatWxPayRefundLogService::wxRefundQueryByPayStatement($this->uuid);
    }

}

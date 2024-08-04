<?php

namespace xjryanse\finance\service\staffFee;

use xjryanse\logic\Arrays;
use Exception;

/**
 * 
 */
trait DoTraits{
    public function doAddStatementBatch($param) {
        // 生成一个付款账单，多单报销合并一单付款
        $ids = Arrays::value($param, 'id');
        return self::addStatementBatch($ids);
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
        return self::packPreGet($ids);
    }
}

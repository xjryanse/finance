<?php

namespace xjryanse\finance\service\accountLog;

use think\facade\Request;
/**
 * 分页复用列表
 */
trait PaginateTraits{

    
    /**
     * 某一类型的账单，单独管理
     */
    public static function paginateWithBillType($con = [], $order = '', $perPage = 10, $having = '', $field = "*") {
        $statementType = Request::param('bill_type');
        
        $con[] = ['bill_type','=',$statementType];
        $res = self::paginateX($con);

        return $res;
    }
}

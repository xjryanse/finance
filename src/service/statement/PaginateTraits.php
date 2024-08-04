<?php

namespace xjryanse\finance\service\statement;

use think\facade\Request;
/**
 * 
 */
trait PaginateTraits{

    /**
     * 某一类型的账单，单独管理
     * 只查状态开
     */
    public static function paginateWithStatementType($con = [], $order = '', $perPage = 10, $having = '', $field = "*") {
        $statementType = Request::param('statement_type');
        
        $con[] = ['statement_type','=',$statementType];
        $withSum = true;
        $res = self::paginateX($con, $order, $perPage, $having, $field, $withSum);

        return $res;
    }
    

    
}

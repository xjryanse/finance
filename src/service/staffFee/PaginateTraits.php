<?php

namespace xjryanse\finance\service\staffFee;

use xjryanse\logic\Arrays2d;
use xjryanse\logic\ModelQueryCon;
use think\facade\Request;
use think\Db;
/**
 * 分页复用列表
 */
trait PaginateTraits{
    /**
     * 20230522：客户管理员视角
     * 20240409:因逻辑调整，几乎可以弃用了（加油和维修，报销数据有两份，源表1份；报销单1份）
     * @param type $con
     */
    public static function paginateForBaoStaffFee($con) {
        $param = Request::param('table_data') ? : [];
        $fields = [];
        $fields['equal']    = ['user_id','bus_id','owner_type','hasSettle','hasStatement'];
        $fields['timescope'] = ['apply_time'];
        $con = ModelQueryCon::queryCon($param, $fields);

        // 20231231
        $tableSql = self::staffFeeSqlWithOtherTable();
        
        $data = Db::table($tableSql)->where($con)->order('apply_time desc,id')->paginate(50);
        
        $res = $data ? $data->toArray() : [];
        
        $res['$tableSql'] = $tableSql;
        // 20231231:单图，多图处理
        $res['data'] = Arrays2d::picFieldCov($res['data'], ['annex']);
        $res['data'] = Arrays2d::multiPicFieldCov($res['data'], ['file']);
        $res['withSum'] = 1;
        $res['sumData'] = Db::table($tableSql)->where($con)->field('sum(money) as money')->find();
        
        return $res;
    }
    
}

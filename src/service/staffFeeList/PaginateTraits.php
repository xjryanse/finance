<?php

namespace xjryanse\finance\service\staffFeeList;

use xjryanse\finance\service\FinanceStaffFeeTypeService;
use xjryanse\logic\Datetime;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\ModelQueryCon;
use xjryanse\logic\DbOperate;
use think\facade\Request;
use think\Db;
/**
 * 
 */
trait PaginateTraits {
    /**
     * 部门车辆统计
     * @return type
     */
    public static function paginateForDeptBusStatics(){
        $param          = Request::param('table_data');
        if(!Arrays::value($param , 'yearmonth')){
            $param['yearmonth'] = date('Y-m');
        }

        $scopeTimeArr   = Datetime::paramScopeTime($param);
        $con            = [];
        if($scopeTimeArr){
            $con[]  = ['tB.apply_time','>=',$scopeTimeArr[0]];
            $con[]  = ['tB.apply_time','<=',$scopeTimeArr[1]];
        }

        $listsSql   = self::mainModel()->deptBusStaticsListSql($con);
        $lists      = Db::query($listsSql);
        
        Arrays2d::pushTimeField($lists, $param);
        // 【2】处理动态字段？？
        $items = FinanceStaffFeeTypeService::where()->select();
        foreach($items as $i){
            // 20230604:控制前端页面显示的动态站点:字段格式：universal_item_table表
            $res['fdynFields'][] = ['id' => self::mainModel()->newId(), 'name' => 'm'.$i['id'], 'label' => $i['fee_name']
                    , 'type' => 'number'
                    , 'option'=>[
                        'positive'  => 'b6 f-blue',
                        'negative'  => 'f-gray'
                    ]
                    , 'sortable'=>1
                ];
        }
        //【3】处理求和？？
        $res['sumData'] = [];
        foreach ($items as $item) {
            $key = 'm'.$item['id'];
            $res['sumData'][$key] = round(Arrays2d::sum($lists, $key),2);
        }
        $res['sumData']['money'] = round(Arrays2d::sum($lists, 'money'),2);
        
        $res['withSum'] = 1;        
        $res['data']    = $lists;
        return $res;
    }

    
    /**
     * 带其他表数据的报销费用列表
     * @return type
     */
    public static function paginateStaffFeeListWithOtherTable(){
        $param          = Request::param('table_data');
        
        $fields['equal']        = ['order_id', 'bao_bus_id', 'bus_id', 'user_id'];
        $fields['timescope']    = ['apply_time'];
        $con = ModelQueryCon::queryCon($param, $fields);
        // 时间查询
        $scopeTimeArr   = Datetime::paramScopeTime($param);
        if($scopeTimeArr){
            $con[]  = ['apply_time','>=',$scopeTimeArr[0]];
            $con[]  = ['apply_time','<=',$scopeTimeArr[1]];
        }

        $listSql = self::mainModel()->staffFeeListSql() .' as mainTable';
        $lists = Db::table($listSql)->where($con)->paginate(50);

        $listsArr = $lists ? $lists->toArray() : [];
        // 求和字段
        $sumFields = ['money'];
        $fieldStr = DbOperate::sumFieldStr($sumFields);
        $listsArr['sumData'] = Db::table($listSql)->where($con)->field($fieldStr)->find();
        $listsArr['withSum'] = 1;

        return $listsArr;
    }

    
}

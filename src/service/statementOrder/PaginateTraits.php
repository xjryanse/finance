<?php

namespace xjryanse\finance\service\statementOrder;

use xjryanse\customer\service\CustomerUserService;
use xjryanse\logic\Arrays;
/**
 * 分页复用列表
 */
trait PaginateTraits{
    /**
     * 20230522：客户管理员视角
     * @param type $con
     */
    public static function paginateForCustomerManager($con) {
        // 只提取管理员
        $customerIds    = CustomerUserService::userManageCustomerIds(session(SESSION_USER_ID));
        $con[]          = ['customer_id', 'in', $customerIds];
        $lists = self::paginateRaw($con,'', 10, '', '*', true);
        return $lists;
    }
    
    /**
     * 
     * 20230522：后台查询，按客户聚合，查询账单
     * @param $ids
     */
    public static function listForAdmCustomerGroup($ids = []) {
        $con = [];

        $fields = [];
        $fields[] = 'customer_id';
        $fields[] = 'count(1) as totalCount';
        $fields[] = 'sum(if (has_settle,0,1)) as noSettleCount';
        $fields[] = 'sum(if (has_settle,1,0)) as settleCount';
        $fields[] = 'sum(need_pay_prize) as totalPrize';
        $fields[] = 'sum(if (has_settle,0,need_pay_prize)) as noSettlePrize';
        $fields[] = 'sum(if (has_settle,need_pay_prize,0)) as settlePrize';
        // 待结应结-笔数
        $fields[] = 'sum(if (has_settle = 0 and is_needpay = 1,1,0)) as noSettleNeedPayCount';
        // 待结应结-金额
        $fields[] = 'sum(if (has_settle = 0 and is_needpay = 1,need_pay_prize,0)) as noSettleNeedPayPrize';

        $lists = self::where($con)
                ->field(implode(',', $fields))
                ->group('customer_id')->select();
        $listsArr  =    $lists ? $lists->toArray() : [];
        // 用户数
        $customerUsers = CustomerUserService::groupBatchCount('customer_id', array_column($listsArr,'customer_id'));
        // 管理数
        $cone = [['is_manager','=',1]];
        $customerManagers = CustomerUserService::groupBatchCount('customer_id', array_column($listsArr,'customer_id'), $cone);
        foreach($listsArr as &$v){
            $v['customerUserCount'] = Arrays::value($customerUsers, $v['customer_id'], 0);
            // 有用户，控制前端显示
            $v['hasUser'] = $v['customerUserCount'] ? 1 : 0;
            
            $v['customerManagerCount'] = Arrays::value($customerManagers, $v['customer_id'], 0);
            // 有管理员，控制前端显示
            $v['hasManager'] = $v['customerManagerCount'] ? 1 : 0;
        }
        
        return $listsArr;
    }

}

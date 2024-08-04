<?php

namespace xjryanse\finance\service\manageAccountLog;

/**
 * 分页复用列表
 */
trait PaginateTraits{
    /**
     * 20230522：客户管理员视角
     * @param type $con
     */
    public static function paginateBalance($con) {
        $con[] = ['company_id','=',session(SESSION_COMPANY_ID)];
        
        $groups = ['manage_account_id','balance_id','company_id'];

        $fields     = $groups;
        $fields[]   = "max(create_time) as balanceTime";
        $fields[]   = "sum(if(change_type = 1,1,0)) as accountLogCount";
        $fields[]   = "sum(if(change_type = 1,money,0)) as accountLogMoney";
        $fields[]   = "sum(if(change_type = 2,0,1)) as statementCount";
        $fields[]   = "sum(if(change_type = 2,0,money)) as statementMoney";
        $fields[]   = "sum(money) as diffMoney";
        
        $resInst = self::mainModel()->where($con)
                ->field(implode(',',$fields))
                ->group(implode(',',$groups));

        $res = $resInst->paginate();
        $resp = $res ? $res->toArray() : [];
        
        return $resp;
        
        
        // 只提取管理员
//        $arr[] = ['manage_account_id'=>1,'balanceTime'=>'2023','statementCount'=>2,'statementMoney'=>3,'accountLogCount'=>4,'accountLogMoney'=>5];
//        $arr[] = ['manage_account_id'=>1,'balanceTime'=>'2023','statementCount'=>2,'statementMoney'=>3,'accountLogCount'=>4,'accountLogMoney'=>5];
//        $arr[] = ['manage_account_id'=>1,'balanceTime'=>'2023','statementCount'=>2,'statementMoney'=>3,'accountLogCount'=>4,'accountLogMoney'=>5];
//        $arr[] = ['manage_account_id'=>1,'balanceTime'=>'2023','statementCount'=>2,'statementMoney'=>3,'accountLogCount'=>4,'accountLogMoney'=>5];
    }


}

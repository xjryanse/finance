<?php

namespace xjryanse\finance\service\accountLog;

use xjryanse\logic\Arrays;
use xjryanse\logic\Datetime;
use xjryanse\finance\service\FinanceAccountService;
use think\Db;
/**
 * 分页复用列表
 */
trait ListTraits{
    /**
     * 日统计
     */
    public static function listForDailyStatics($param){
        // $lists = [];
        $yearmonth  = Arrays::value($param, 'yearmonth') ? : date('Y-m');
        $date       = Arrays::value($param, 'date');
        // 默认取账单时间
        $timeKey    = Arrays::value($param, 'timeKey') ? : 'bill_time';
        $con = Datetime::yearMonthTimeCon($timeKey, $yearmonth, $date);
        $accountId  = Arrays::value($param, 'account_id');
        if($accountId){
            $con[] = ['account_id','=',$accountId];
        }

        $sql = self::mainModel()->sqlGroupDownForDailyStatics($timeKey,$con);

        $lists = Db::query($sql);
        return $lists;
    }
    
    /**
     * 月统计
     */
    public static function listForMonthlyStatics($param){
        // $lists = [];
        $year       = Arrays::value($param, 'year') ? : date('Y');
        $month      = Arrays::value($param, 'month');
        // 默认取账单时间
        $timeKey    = Arrays::value($param, 'timeKey') ? : 'bill_time';
        $con = Datetime::yearTimeCon($timeKey, $year, $month);
        $accountId  = Arrays::value($param, 'account_id');
        if($accountId){
            $con[] = ['account_id','=',$accountId];
        }

        $sql = self::mainModel()->sqlGroupDownForMonthlyStatics($timeKey,$con);

        $lists = Db::query($sql);
        return $lists;
    }
    
    /**
     * 年统计
     */
    public static function listForYearlyStatics($param){
        // $lists = [];
        $year       = Arrays::value($param, 'year') ? : date('Y');
        // $month      = Arrays::value($param, 'month');
        // 默认取账单时间
        $timeKey    = Arrays::value($param, 'timeKey') ? : 'bill_time';
        $con        = Datetime::yearTimeCon($timeKey, $year);
        $accountId  = Arrays::value($param, 'account_id');
        if($accountId){
            $con[] = ['account_id','=',$accountId];
        }

        $sql = self::mainModel()->sqlGroupDownForYearlyStatics($timeKey,$con);

        $lists = Db::query($sql);
        return $lists;
    }
    
    
    /**
     * 手机端日明细查询
     */
    public static function listForDailyList($param){
        $con = [];
        $lists = self::where()->limit(12)->select();
        
        $listsArr = $lists ? $lists->toArray() : [];
        return $listsArr;
    }

    /**
     * 20231115：收入流水
     * @param type $param
     * @return type
     */
    public static function listForDailyIncomeList($param){
        
        $date = Arrays::value($param, 'date') ? : date('Y-m-d');
        $startTime  = Datetime::dateStartTime($date);
        $endTime    = Datetime::dateEndTime($date);
        
        
        $con    = [];
        // 只查收入
        $con[]    = ['change_type','=',1];
        $con[]    = ['bill_time','>=',$startTime];
        $con[]    = ['bill_time','<=',$endTime];
        // 只查手工记账
        $accountIds = FinanceAccountService::calHandleAccountIds();
        $con[]    = ['account_id','in',$accountIds];

        $lists  = self::where($con)->order('account_id')->select();

        $listsArr = $lists ? $lists->toArray() : [];
        return $listsArr;
    }
    
    public static function listForDailyOutcomeList($param){
        
        $date = Arrays::value($param, 'date') ? : date('Y-m-d');
        $startTime  = Datetime::dateStartTime($date);
        $endTime    = Datetime::dateEndTime($date);
        
        $con    = [];
        // 只查收入
        $con[]    = ['change_type','=',2];
        $con[]    = ['bill_time','>=',$startTime];
        $con[]    = ['bill_time','<=',$endTime];
        // 只查手工记账
        $accountIds = FinanceAccountService::calHandleAccountIds();
        $con[]    = ['account_id','in',$accountIds];

        $lists  = self::where($con)->order('account_id')->select();

        $listsArr = $lists ? $lists->toArray() : [];
        return $listsArr;
    }
}

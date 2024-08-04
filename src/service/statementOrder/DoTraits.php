<?php

namespace xjryanse\finance\service\statementOrder;

use think\facade\Request;
use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
use xjryanse\logic\DbOperate;
use Exception;
/**
 * 计算类
 */
trait DoTraits{

    /**
     * 前台打包生成账单的操作
     * @param type $param
     * @return type
     */
    public static function doStatementGenerate($param){
        $data   = Arrays::value($param, 'table_data') ? $param['table_data']: $param;
        // 批量入账
        $ids    = Arrays::value($data, 'id');
        // belong_table_id:单条入账
        $belongTableId = Arrays::value($data, 'belong_table_id');
        if(!$ids && !$belongTableId){
            throw new Exception('参数不全'.$belongTableId);
        }
        // 20240119：业务确认的入账账户
        $sData['confirm_account_id']    = Arrays::value($data, 'confirm_account_id');
        $sData['confirm_user_id']       = session(SESSION_USER_ID);
        $sData['confirm_time']          = date('Y-m-d H:i:s');
        // 20240708
        $sData['has_confirm']           = 1;
        $sData['remark']                = Arrays::value($data, 'remark');
        $sData['is_un_prize']           = Arrays::value($data, 'is_un_prize');

        if($belongTableId){
            // TODO
            $prizeKey                       = 'GoodsPrize';
            $stOrData['belong_table_id']    = $belongTableId;
            $stOrData['belong_table']       = Arrays::value($data, 'belong_table');
            $stOrData['is_un_prize']        = Arrays::value($data, 'is_un_prize');

            $service = $stOrData['belong_table'] ? DbOperate::getService($stOrData['belong_table'] ) : '';
            if($service && $stOrData['belong_table_id']){
                $info                   = $service::getInstance($belongTableId)->get();
                // 适用于订单明细结算
                $stOrData['order_id']   = Arrays::value($info, 'order_id');
                // 适用于订单明细结算
                $stOrData['sub_id']     = $belongTableId;
            }

            $money                          = Arrays::value($data, 'money');
            $statementOrderInfo             = self::prizeGetIdForSettle($prizeKey, $money, $stOrData);
            $ids = $statementOrderInfo['id'];
        }

        $info = self::statementGenerateRam($ids, $sData);
        return $info;
    }

}

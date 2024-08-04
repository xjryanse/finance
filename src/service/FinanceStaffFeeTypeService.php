<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\logic\Arrays2d;

/**
 * 
 */
class FinanceStaffFeeTypeService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;
    use \xjryanse\traits\StaticModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFeeType';

    use \xjryanse\finance\service\staffFeeType\FieldTraits;
    use \xjryanse\finance\service\staffFeeType\TriggerTraits;
    
    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {

                    $cond[] = ['fee_id', 'in', $ids];
                    $feeListsObj = FinanceStaffFeeListService::where($cond)->select();
                    $feeLists = $feeListsObj ? $feeListsObj->toArray() : [];

                    foreach ($lists as &$v) {
                        //是否刚添加的记录,4小时内
                        $v['isRecent'] = time() > strtotime($v['create_time']) && (time() - strtotime($v['create_time'])) < 3600 * 4 ? 1 : 0;
                        //驾驶员
                        $con = [];
                        $con[] = ['fee_id', '=', $v['id']];
                        $feeArr = Arrays2d::listFilter($feeLists, $con);
                        $v['feeArr'] = array_column($feeArr, 'money', 'fee_type');
                    }

                    return $lists;
                },true);
    }
    
    /**
     * key  转id
     * @param type $key
     * @return type
     */
    public static function keyToId($key) {
        $con[] = ['fee_key', '=', $key];
        $arrs = self::staticConList($con);
        return $arrs ? $arrs[0]['id'] : '';
    }
}

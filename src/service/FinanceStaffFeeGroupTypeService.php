<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;

/**
 * 
 */
class FinanceStaffFeeGroupTypeService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\ObjectAttrTrait;
    use \xjryanse\traits\StaticModelTrait;
    // use \xjryanse\approval\traits\ApprovalOutTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceStaffFeeGroupType';
    //直接执行后续触发动作
    protected static $directAfter = true;

    /**
     * 费用分组，提取费用类型列表
     */
    public static function dimTypeIdsByFeeGroup($feeGroup){
        $con    = [];
        $con[]  = ['fee_group', '=', $feeGroup];
        
        return self::staticConColumn('fee_type_id');
    }
    
    
    
    
}

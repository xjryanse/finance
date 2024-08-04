<?php

namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;

/**
 */
class FinanceTimeKeyService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    use \xjryanse\traits\StaticModelTrait;
    
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceTimeKey';
    //直接执行后续触发动作
    protected static $directAfter = true;

    public static function keysForInit(){
        return self::staticConList();
    }
    
}

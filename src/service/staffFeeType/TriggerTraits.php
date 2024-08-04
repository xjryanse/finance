<?php

namespace xjryanse\finance\service\staffFeeType;

/**
 * 
 */
trait TriggerTraits{
    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        self::stopUse(__METHOD__);
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete() {
        self::stopUse(__METHOD__);
    }
}

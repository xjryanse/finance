<?php
namespace xjryanse\finance\interfaces;

/**
 * 费用报销来源表接口
 */
interface StaffFeeOutInterface
{
    /**
     * 数据同步写入报销明细
     */
    public function staffFeeSync();
}

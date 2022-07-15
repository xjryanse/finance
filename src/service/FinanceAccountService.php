<?php

namespace xjryanse\finance\service;

use think\Db;
use Exception;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Sql;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Debug;
use xjryanse\system\service\SystemCompanyService;
/**
 * 账户表
 */
class FinanceAccountService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceAccount';
    //直接执行后续触发动作
    protected static $directAfter = true;    
    
    /*
     * 用户id和账户类型创建，一个类型只能有一个账户
     */
    public static function createAccount( $accountType ,$data = [])
    {
        $con[] = ['account_type','=',$accountType];
        $data['account_type']   = $accountType;
        return self::count($con) ? false : self::save( $data );
    }
    /**
     * 根据账户类型取id
     * @param type $companyId   公司id
     * @param type $accountType 账户类型
     * @return type
     */
    public static function getIdByAccountType( $companyId, $accountType )
    {
        $con[] = ['account_type','=',$accountType];
        $listsAll   = SystemCompanyService::getInstance($companyId)->objAttrsList('financeAccount');
        $info       = Arrays2d::listFind($listsAll, $con);
        if(!$info){
            $info = self::createAccount($accountType);
        }
        return $info ? $info['id'] : '';
    }
    
    public function extraPreDelete()
    {
        self::checkTransaction();
        $con[] = ['account_id','=',$this->uuid];
        $res = FinanceAccountLogService::mainModel()->where($con)->count(1);
        if($res){
            throw new Exception('该账户有流水，不可删除');
        }
    }
        
    /**
     * 入账更新
     */
    public function income( $value ) {
        self::checkTransaction();
        //账户余额更新
        return self::mainModel()->where('id', $this->uuid)->setInc('money', $value);
    }    
    
    /**
     * 资金出账更新
     */
    public function outcome($value) {
        self::checkTransaction();
        //账户余额更新
        return self::mainModel()->where('id', $this->uuid)->setDec('money', $value);
    }    
    /**
     * 更新余额
     */
    public function updateRemainMoney()
    {
//        $con[] = ['account_id','=',$this->uuid];
//        $money = FinanceAccountLogService::mainModel()->where($con)->sum('money');
//        return self::mainModel()->where('id',$this->uuid)->update(['money'=>$money]);
        $mainTable  =   self::getTable();
        $mainField  =   "money";
        $dtlTable   =   FinanceAccountLogService::getTable();
        $dtlStaticField     =   "money";
        $dtlUniField        =   "account_id";
        $dtlCon[] = ['main.id','=',$this->uuid];
        $sql = Sql::staticUpdate($mainTable, $mainField, $dtlTable, $dtlStaticField, $dtlUniField,$dtlCon);
        Debug::debug('updateRemainMoney的$sql',$sql);
        return Db::query($sql);
    }
    /**
     * 20220622
     * @global array $glSqlQuery
     * @return boolean
     */
    public function updateRemainMoneyRam()
    {
        global $glSqlQuery;
        $mainTable  =   self::getTable();
        $mainField  =   "money";
        $dtlTable   =   FinanceAccountLogService::getTable();
        $dtlStaticField     =   "money";
        $dtlUniField        =   "account_id";
        $dtlCon[] = ['main.id','=',$this->uuid];
        $sql = Sql::staticUpdate($mainTable, $mainField, $dtlTable, $dtlStaticField, $dtlUniField,$dtlCon);
        //扔一条sql到全局变量，方法执行结束后执行
        $glSqlQuery[] = $sql;
        return true;
    }

    /**
     *
     */
    public function fId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fAppId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fCompanyId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 账户名称
     */
    public function fAccountName() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 账户类型(cash：现金,wechat：微信，corporate：对公账，meituan：美团账户)
     */
    public function fAccountType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 账号
     */
    public function fAccountNo() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 账户余额
     */
    public function fMoney() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 排序
     */
    public function fSort() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 状态(0禁用,1启用)
     */
    public function fStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 有使用(0否,1是)
     */
    public function fHasUsed() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未锁，1：已锁）
     */
    public function fIsLock() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未删，1：已删）
     */
    public function fIsDelete() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 备注
     */
    public function fRemark() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建者，user表
     */
    public function fCreater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新者，user表
     */
    public function fUpdater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建时间
     */
    public function fCreateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新时间
     */
    public function fUpdateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

}

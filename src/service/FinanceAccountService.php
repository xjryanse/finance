<?php

namespace xjryanse\finance\service;

/**
 * 账户表
 */
class FinanceAccountService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\finance\\model\\FinanceAccount';
    
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
        $con[] = ['company_id','=',$companyId];
        $con[] = ['account_type','=',$accountType];

        $info = self::find( $con );
        if(!$info){
            $info = self::createAccount($accountType);
        }
        return $info ? $info['id'] : '';
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
        $con[] = ['account_id','=',$this->uuid];
        $money = FinanceAccountLogService::mainModel()->where($con)->sum('money');
        return self::mainModel()->where('id',$this->uuid)->update(['money'=>$money]);
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

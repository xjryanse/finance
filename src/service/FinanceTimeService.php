<?php
namespace xjryanse\finance\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\user\service\UserService;
use xjryanse\logic\Datetime;
use xjryanse\logic\Arrays;
use xjryanse\logic\Cachex;
use Exception;
/**
 * 
 */
class FinanceTimeService implements MainModelInterface
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\finance\\model\\FinanceTime';
    //直接执行后续触发动作
    protected static $directAfter = true;

    /**
     * 改写MainModelTrait方法
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     * @param type $withSum
     * @return type
     */
    public static function paginate( $con = [],$order='',$perPage=10,$having = '',$field="*", $withSum=false)
    {
        //初始化账期数据，节省人工添加的承包
        self::init();
        return self::paginateX($con, $order, $perPage, $having, $field, $withSum);
    }
    
    /**
     * 初始化账期数据
     */
    public static function init(){
        $con[] = ['company_id','=',session(SESSION_COMPANY_ID)];
        $info = self::mainModel()->where($con)->order('to_time desc')->limit(1)->find();

        $endTime    = date('Y-m-d H:i:s');
        //开始日期，已有账期最后一个日期+一天
        $startTime  = $info ? date('Y-m-d H:i:s',strtotime($info['to_time']) + 86400) : date('Y-01-01 00:00:00') ;

        $arr = Datetime::getWithinMonth($startTime, $endTime);
        foreach($arr as $v){
            $data = [];
            $data['belong_time'] = $v;
            $data['from_time'] = $v.'-01 00:00:00';
            $data['to_time'] = date('Y-m-d 23:59:59',strtotime('+1 month -1 day',strtotime($v.'-01')));
            self::save($data);
        }
    }
    
    /**
     * 20220617:设定缓存；一般用于锁账后；
     */
    protected static function setLockTimeArrCache(){
        $cacheKey = __CLASS__.'getLockTimesArr';
        return Cachex::set($cacheKey, function(){
            return self::lockTimeArrDb();
        },true);
    }    
    /**
     * 获取锁定时间段
     * @param type $cacheUpdate     
     * @return type
     */
    protected static function getLockTimesArrCache(){
        $cacheKey = __CLASS__.'getLockTimesArr';
        return Cachex::funcGet($cacheKey, function(){
            return self::lockTimeArrDb();
        },true);
    }
    /**
     * 20220617;从数据库获取
     * @return type
     */
    protected static function lockTimeArrDb(){
        $con[] = ['time_lock','=',1];
        $lists = self::lists($con);
        return $lists ? $lists->toArray() : [];
    }
    
    /**
     * 传入一个时间，获取被锁定的账期名
     */
    public static function isTimeLock($time){
        $lockArr = self::getLockTimesArrCache();
        foreach($lockArr as &$v){
            if($v['from_time'] <= $time && $v['to_time'] >= $time){
                return $v['id'];
            }
        }
        return false;
    }
    /**
     * 校验时间是否被锁
     * @param type $time
     * @throws Exception
     */
    public static function checkLock($time){
        $lockTimeId = self::isTimeLock( $time );
        if($lockTimeId){
            $info       = FinanceTimeService::getInstance($lockTimeId)->get();
            $lockUserId = $info['lock_user_id'];
            $userInfo   = UserService::getInstance($lockUserId)->get();
            $namePhone  = Arrays::value($userInfo, 'namePhone');
            throw new Exception('账期'.$info['belong_time'].'已被"'.$namePhone.'"锁定，请联系财务');
        }
    }
    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        
    }
    /**
     * 钩子-保存后
     */
    public static function extraAfterSave(&$data, $uuid) {

        //20220617
        self::setLockTimeArrCache();
    }
    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        if(Arrays::value($data, 'time_lock')){
            $info = self::getInstance($uuid)->get();
            if(!Datetime::isExpire($info['to_time'])){
                throw new Exception('时间'.$info['to_time'].'未过，还可能产生业务数据，不可关账');
            }
            $data['lock_user_id'] = session(SESSION_USER_ID);
        } else {
            if(isset($data['time_lock'])){
                $data['lock_user_id'] = null;
            }
        }
    }
    /**
     * 钩子-更新后
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        //20220617
        self::setLockTimeArrCache();
    }
    
    /**
     * 钩子-删除前
     */
    public function extraPreDelete()
    {

    }
    /**
     * 钩子-删除后
     */
    public function extraAfterDelete()
    {

    }
    
    /**
	 *会计状态：0待审批；1已同意，2已拒绝
	 */
	public function fAccStatus()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *附件
	 */
	public function fAnnex()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *申请时间
	 */
	public function fApplyTime()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *业务状态：0待审批；1已同意，2已拒绝
	 */
	public function fBossStatus()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *车号
	 */
	public function fBusId()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *
	 */
	public function fCompanyId()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *创建时间
	 */
	public function fCreateTime()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *创建者，user表
	 */
	public function fCreater()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *费用归属：office办公室；driver司机；
	 */
	public function fFeeGroup()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *报销单号
	 */
	public function fFeeSn()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *出纳状态：0待审批；1已同意，2已拒绝
	 */
	public function fFinStatus()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *有使用(0否,1是)
	 */
	public function fHasUsed()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *
	 */
	public function fId()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *锁定（0：未删，1：已删）
	 */
	public function fIsDelete()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *锁定（0：未锁，1：已锁）
	 */
	public function fIsLock()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *报销金额
	 */
	public function fMoney()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *状态：0待支付；1已支付
	 */
	public function fPayStatus()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *备注
	 */
	public function fRemark()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *排序
	 */
	public function fSort()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *状态(0禁用,1启用)
	 */
	public function fStatus()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *报销类别
	 */
	public function fType()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *更新时间
	 */
	public function fUpdateTime()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *更新者，user表
	 */
	public function fUpdater()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	/**
	 *报销人
	 */
	public function fUserId()
	{
		return $this->getFFieldValue(__FUNCTION__);	
	}
	
}

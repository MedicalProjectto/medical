<?php
/**
 * desc: 用户相关方法
 *
*/

class Muser extends CHookModel {

    private $user     = 'user';
    private $tProfile = 'user_profile';
    private $tPass    = 'user_pass';
    private $tPatient = 'user_patient';
    private $AESKEY   = '!@#$&FD&9Nhi$R%uhdiw';
    private $charArr  = array('.','`','~','@','$','%','^','&','*','-','_','=','+','(',')');

    private $maxPatients = 500;
    
    /*
    * desc: 获取多条员工记录
    *
    */
    public function getUsers($userids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $userids){
            if(is_array($userids)){
                $whArr = array_merge(array('id in'=>$userids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$userids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated   = isset($exArr['aggregated'])?$exArr['aggregated']:false;
        $join_profile = isset($exArr['join_profile'])?$exArr['join_profile']:false;
        $join_pass    = isset($exArr['join_pass'])?$exArr['join_pass']:false;
        $join_wx      = isset($exArr['join_wx'])?$exArr['join_wx']:false;
        $join_hospital= isset($exArr['join_hospital'])?$exArr['join_hospital']:false;

        if($join_profile){
            $exArr['join']['user_profile profile'] = "id:userid";
        }
        if($join_pass){
            $exArr['join']['user_pass'] = "id:userid";
        }
        if($join_wx){
            $exArr['join']['user_wx'] = "id:userid";
        }
        /***************************join end********************/
        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->user, $whArr, $exArr);
        if(!$dataArr)return $dataArr;
        //业务处理...
        if(!$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_hospital){
                $MHospital = $this->LoadApiModelMedical('hospital');
                $hid_arr = $this->getArrayColumn($rowArr,'hospitalid');
                
                $hospArr = $MHospital->getHospitals($hid_arr, null, array('limit'=>count($hid_arr),'only_data'=>true,'keyas'=>'id','join_val'=>true, 'fields'=>'id,title,adminid,mobile,contact'));
                $rowArr = $this->joinToArray($rowArr, $hospArr,'hospitalid:id','hospital');
            }
        }
        return $dataArr;
    }

    public function getUser($userid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $userid){
            $whArr = array_merge(array('id'=>$userid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getUsers(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }

    //整理更新时的post表单数据
    private function _trim_update_data(&$addArr)
    {
        $addArr['utime'] = date('Y-m-d H:i:s');
    }
    //整理添加时的post表单数据
    private function _trim_add_data(&$addArr)
    {
        $this->_trim_update_data($addArr);
        $addArr['ctime'] = $addArr['utime'] = date('Y-m-d H:i:s');
    }

    /*
    * desc: 添加一个员工
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               user   --- 员工信息
    *               )
    *
    */
    public function addUser($postArr)
    {
        $logfile = 'addUser';
        CLog::WriteLog(array('msg'=>'所有参数', 'postArr'=>$postArr), $logfile);
        $retArr = array('status'=>0, 'message'=>'', 'user'=>null);
        // print_r($postArr);exit;
        //数据检查
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['idcard'])) {
            $retArr['message'] = '身份证不能为空';
            return $retArr;
        }
        if(18 != strlen($postArr['idcard'])) {
            $retArr['message'] = '身份证不合法';
            return $retArr;
        }
        $tsbirthday = strtotime(substr($postArr['idcard'],6,8));
        if(false === $tsbirthday){
            $retArr['message'] = '不合法的身份证';
            return $retArr;
        }
        $postArr['age'] = (time()-$tsbirthday)/31536000;
        if(empty($postArr['role'])) {
            $retArr['message'] = '角色不能为空';
            return $retArr;
        }
        /*
        if(empty($postArr['mobile'])) {
            $retArr['message'] = '手机和用户名不能同时为空';
            return $retArr;
        }*/
        //end 数据检查

        //验证是否存在
        $postArr = array_merge(array('mobile'=>'','idcard'=>''), $postArr);
        if(!empty($postArr['mobile']) || !empty($postArr['idcard'])){
            $whArr = array(
                'or' => array(
                    array(
                        'mobile<>' => '',
                        'mobile'   => $postArr['mobile'],
                    ),
                    array(
                        'idcard<>' => '',
                        'idcard'   => $postArr['idcard'],
                    )
                )
            );
            $old = $this->getUser(null, $whArr);
            if($old){
                $retArr['status'] = -1;
                if ($old['idcard'] == $postArr['idcard']) {
                    $retArr['message'] = '该身份证已存在';
                } else if ($old['mobile'] == $postArr['mobile']) {
                    $retArr['message'] = '该手机已存在';
                }
                $retArr['user'] = $old;
                return $retArr;
            }
        }

        //end 验证是否存在

        $this->_trim_add_data($postArr);  //整理数据
        $id = $this->addAtom($this->user, $postArr);

        if($id){
            $profile = $postArr;
            $profile['userid'] = $id;
            $postArr['id'] = $id;
            $this->addAtom($this->tProfile, $profile);
            
            $retArr['status'] = 1;
            $retArr['message'] = '添加用户成功';

            //设置密码
            $plain = !empty($postArr['plain'])?$postArr['plain']:substr($postArr['mobile'], -6); //这是明文密码
            $plain = $this->repassword($id,$plain,$plain);
            $retArr['user']['plain'] = $plain;
            //end设置密码
            $retArr['user'] = $postArr;
        }else{
            $retArr['message'] = '系统繁忙';
        }
        return $retArr;
    }

    /*
    * desc: 更新一个商户(商家)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               user   --- 商户信息
    *               )
    *
    */
    public function updateUser($userid, $postArr)
    {
        $logfile = 'updateUser';
        $retArr = array('status'=>0, 'message'=>'', 'user'=>null);

        CLog::WriteLog(array('msg'=>'所有参数', 'userid'=>$userid, 'postArr'=>$postArr), $logfile);
        //数据检查
        if(empty($postArr) || !$userid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getuser($userid);
        if(!$old){
            $retArr['message'] = '员工不存在!';
            return $retArr;
        }
        if(!empty($postArr['mobile'])) {
            $old = $this->getuser(null, array('mobile'=>$postArr['mobile']));
            if($old){
                $retArr['message'] = '该手机已存在';
                return $retArr;
            }
        }
        //end 数据检查

        $this->_trim_update_data($postArr);  //整理数据
        $ok = $this->updateData($this->user, $postArr, $userid);
        if(false !== $ok){
            if($this->getAtom($this->tProfile,array('userid'=>$userid))){
                $this->updateData($this->tProfile, $postArr, array('userid'=>$userid));
            }else{
                $postArr['userid'] = $userid;
                $this->addAtom($this->tProfile, $postArr);
            }
            
            $retArr['message'] = '修改成功';
            $retArr['status']  = 1;
        }else{
            $retArr['message'] = '修改失败';
        }
        return $retArr;
    }

    /*
    * desc: 切底删除员工
    *
    */
    public function dropUser($userid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'user'=>null);

        //数据检查
        if(!$userid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getUser($userid);
        if(!$old){
            $retArr['message'] = '商户不存在!';
            return $retArr;
        }

        $ok = $this->deleteData($this->user, $userid);
        if($ok){
            $this->deleteData($this->tProfile, $userid);
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
            $retArr['user']   = $old;
        }else{
            $retArr['message'] = '数据库错误';
        }
        return $retArr;;
    }

    /*
    * desc: 判断一个用户是否可以被禁用
    *
    *
    *
    */
    public function isDisabled($userid)
    {
        $user = $this->getUser($userid);
        if(!$user)return false;
        $hospital = $this->LoadApiModelMedical('hospital')->getHospital($user['hospitalid']);
        if(!$hospital || -1==intval($hospital['status'])) return true;
        $tableArr = array(
            'project_pi' => array(
                'or' => array(
                    'makerid' => $userid,
                    'piid' => $userid,
                )
            ),
            'project_doctor' => array(
                'or' => array(
                    'doctorid' => $userid,
                    'piid' => $userid,
                )
            ),
            'project_patient' => array(
                'or' => array(
                    'patientid' => $userid,
                    'doctorid' => $userid,
                )
            ),
            'project' => array(
                'or' => array(
                    'userid' => $userid,
                    'hospitalid' => $hospital['id'],
                )
            ),
        );
        foreach($tableArr as $table => $whArr){
            $row = $this->getAtom($table, $whArr);
            if($row) return false; //有活动，不能禁用
        }
        return true;
    }

    /*
        修改密码
        1:成功
    */
    public function repassword($userid, $pass_new1=null, $pass_new2=null, $pass_old=null)
    {
        if(empty($userid)) return false;
        
        $me = $this->getUser($userid,null,array('aggregated'=>true));
        if(is_array($me) && count($me)>0){
            $old    = $this->getAtom($this->tPass,array('userid'=>$userid));
            // $salt   = CTool::uniqueId(10);
            $plain  = $pass_new1?$pass_new1:mt_rand(100000,999999); //这是明文密码
            $password = md5($plain);
            $passArr  = array(
                // 'salt'     => $salt,
                'plain'    => $plain,
                'password' => $password,
                'utime'    => date("Y-m-d H:i:s"),
            );
            if($old){
                if($pass_new1 != $pass_new2) return false;
                if($pass_old  != $old['plain']) return false;
                $ok = $this->updateData($this->tPass, $passArr, array('userid'=>$userid));
            }else{
                $passArr['userid'] = $userid;
                $ok = $this->addAtom($this->tPass, $passArr);
            }
            if($ok){
                return $plain;
            }
        }
        return false;
    }

    /*
    * desc: 用户绑定微信
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               user   --- 商户信息
    *               )
    *
    */
    /*
    * desc: 
    *@mpid --- int 公众号的标识id
    *
    */
    public function addWxUser($openid, $wxtype, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'wxuser'=>null);

        $old = $this->getWxUser($openid, array('wxtype'=>$wxtype));
        if($old){
            //更新user信息从微信返回的数据===========
            $uuptArr = $postArr;
            $uuptArr['username'] = $uuptArr['truename'] = isset ($postArr['nickname']) ? $postArr['nickname'] : null;
            $uuptArr['avatar'] = isset ($postArr['headimgurl']) ? $postArr['headimgurl'] : null;

            $this->updateData($this->tUserWx, $postArr, array("openid"=>$openid));
            $old = $this->getWxUser($openid, array('wxtype'=>$wxtype));
            $retArr['wxuser'] = $old;
            return $retArr;
        }
        if(empty($postArr['userid'])){
            $retArr['message'] = '无userid';
            return $retArr;
        }

        $postArr['openid'] = $openid;
        $postArr['wxtype'] = $wxtype;
        $id = $this->addAtom($this->tUserWx, $postArr);
        if($id){
            $retArr['mpuser'] = $this->getWxUser($openid, array('wxtype'=>$wxtype));
        }else{
            // $retArr['message'] = $this->error = $dbUser->getError();
            $retArr['message'] = '系统繁忙';
        }
        return $retArr;
    }

    /*
    * desc: 获取微信用户表的信息(user_wx)
    *
    */
    public function getWxUser($openid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $openid){
            $whArr = array_merge(array('openid'=>$openid), $whArr);
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $row = $this->getAtom($this->tUserWx, $whArr, $exArr);
        if($row){
            return $row;
        }
        return false;
    }

    /*
    * desc: 获取公司成员
    *
    */
    public function getMembers($storeid, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        $whArr = array_merge(array('storeid'=>$storeid), $whArr);
        $exArr['limit']      = 500;
        $exArr['only_data']  = true;
        $exArr['aggregated'] = true;
        $exArr['fields']     = isset($exArr['fields'])?$exArr['fields']:'id,mobile,username';
        return $this->getUsers(null, $whArr, $exArr);
    }


    function sendMail($userid_to, $content='', $title='')
    {
        if(!$userid_to) return false;
        $user = $this->getUser($userid_to);
        // $this->dump($user);
        if(!$user || !isset($user['email'])) return false;
        // echo $openid;
    
        $MMail = $this->LoadApiModel('mail');
        $ret = $MMail->send($user['email'], $content, $title);

        return $ret;
    }

    /*******************************病人相关*********************************/
    /*
    * desc: 获取我的病人
    *
    *@userid --- int
    *
    */
    public function getPatients($userids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        $limit = $maxPatients = $this->maxPatients;

        $_wh_myfriends = $_wh_friendsas = $whArr;
        if(null !== $userids){
            if(is_array($userids)){
                $_wh_myfriends = array_merge(array('userid in'=>$userids), $whArr);
                $_wh_friendsas = array_merge(array('patientid in'=>$userids), $whArr);
                $limit = $maxPatients * $maxPatients;
            }else{
                $_wh_myfriends = array_merge(array('userid'=>$userids), $whArr);
                $_wh_friendsas = array_merge(array('patientid'=>$userids), $whArr);
            }
        }

        $exArr['limit'] = $limit;

        $just_count = isset($exArr['just_count'])?$exArr['just_count']:false; //计划个数
        $user_info  = isset($exArr['user_info'])?$exArr['user_info']:false; //只返回userid
        
        if($just_count){
            $exArr['fields'] = 'count(*) _cnt';
            //1,获取我的朋友----------------
            $cRow1 = $this->getAtom($this->tPatient, $_wh_myfriends, $exArr);
            $cnt_1 = $cRow1?$cRow1['_cnt']:0;

            //2,获取把我当朋友的------------
            $cRow2 = $this->getAtom($this->tPatient, $_wh_friendsas, $exArr);
            $cnt_2 = $cRow2?$cRow2['_cnt']:0;
            return $cnt_1 + $cnt_2;
        }

        //1,获取我的朋友----------------
        
        $ex001     = array_merge(array('fields'=>'patientid'), $exArr);
        $myPatients = $this->getData($this->tPatient, $_wh_myfriends, $ex001);
        $fid_arr_1 = $this->getArrayColumn($myPatients,'patientid');
        if(isset($exArr['ttt'])){
            // $this->dump($_wh_myfriends);
            // exit;
        }
        //2,获取把我当朋友的------------
        $ex002     = array_merge(array('fields'=>'userid'), $exArr);
        $friendsAs = $this->getData($this->tPatient, $_wh_friendsas, $ex002);
        $fid_arr_2 = $this->getArrayColumn($friendsAs,'userid');

        $fidArr = array_unique(array_merge($fid_arr_1, $fid_arr_2));
        sort($fidArr);

        // echo '11111111111111111111';
        // $this->dump($fid_arr_1);$this->dump($fidArr);
        // echo '22222222222222222222';
        if($user_info){
            $friendArr = $this->getUsers($fidArr, null, array('only_data'=>true, 'limit'=>count($fidArr)));
            return $friendArr;
        }
        return $fidArr;
    }
    /*
    * desc: 获取我的病人个数
    *
    *@userid --- int
    *
    */
    public function getPatientCount($userid=null, $whArr=array(), $exArr=array())
    {
        $exArr = is_array($exArr)?$exArr:array();
        $exArr['just_count'] = true;
        return $this->getPatients($userid, $whArr, $exArr);
    }

    /*
    * desc: 添加一条病人关系
    *
    *@userid   --- int
    *@patientid --- int "病人"userid
    *
    */
    public function addPatient($userid, $patientid, $postArr=array())
    {
        $retArr = array('status'=>0, 'message'=>'系统繁忙，稍候再试', 'friend'=>null);
        if(intval($userid)<=0 || intval($patientid)<=0){
            $retArr['message'] = '参数错误';
            return $retArr;
        }

        $uniqueid = CFun::touniqueidArgs($userid, $patientid);

        $postArr = array_merge($postArr, array(
            'id'        => $uniqueid,
            'userid'    => $userid,
            'patientid' => $patientid,
            'ctime'     => date("Y-m-d H:i:s"),
        ));

        $old = $this->getAtom($this->tPatient, $uniqueid);
        if($old){
            $retArr['status']  = 1;
            $retArr['message'] = '已是病人关系';
            $retArr['friend']  = $old;
        }
        $friendCount = $this->getPatientCount($userid); //我的病人数
        if($friendCount >= $this->maxPatients){
            $retArr['message'] = '病人数已达到上限';
            return $retArr;
        }

        $id = $this->addAtom($this->tPatient, $postArr);
        if($id){
            $friend = $this->getAtom($this->tPatient, $id);
            $retArr['status']  = 1;
            $retArr['message'] = '添加病人成功';
            $retArr['friend']  = $friend;
        }
        return $retArr;
    }

    /*
    * desc: 删除病人关系
    *
    *@userid   --- int
    *@patientid --- int "病人"userid
    *
    */
    public function dropPatient($userid, $patientid)
    {
        $uniqueid = CFun::touniqueidArgs($userid, $patientid);
        return $this->dropBuddy($uniqueid);
    }

    /*******************************病人相关end******************************/

    /*
    * desc: 通过修改时间模拟踢用户下线
    *
    *
    */
    function offline($userid)
    {
        $this->updateUser($userid, array('lastime'=>date("Y-m-d H:i:s")));
    }

    //token相关===============================================
    function strInsert($string, $pos, $substr) 
    { 
        $pos = intval($pos);
        $startstr = substr($string, 0, $pos);
        $laststr  = substr($string, $pos);
        return $startstr . $substr . $laststr;
    }
    /*
    * desc: 生成token
    *    17   01  14470394381
    *   位置 长度    数据
    */
    function makeToken($userid, $datetime)
    {
        if(strlen($datetime) < 2)return null;
        $aes = new CAes($this->AESKEY);

        $times  = strtotime($datetime) - 1440000000; //为了减小时间的长度 最终减小token的长度
        // var_dump(strtotime($datetime));
        $pU = base_convert($userid, 10, 36);
        $pT = base_convert($times, 10, 36);
        $charArr = &$this->charArr;
        shuffle($charArr);
        $sp = current($charArr);
        $plain  = "{$pU}{$sp}{$pT}";
        
        return $aes->encrypt($plain);
    }
    /*
    * desc: 生成token
    *
    */
    function deToken($token)
    {
        $empty = array(0,0);
        try{
            $aes = new CAes($this->AESKEY);
            $plain = $aes->decrypt($token);
        }catch(Exception $e){
            return $empty;
        }
        $plain = str_replace($this->charArr, '.', $plain);
        if(!strpos($plain, '.'))return $empty;
        list($pU, $pT) = explode('.', $plain);
        $userid  = base_convert($pU, 36, 10);
        $times   = base_convert($pT, 36, 10);
        $times = floatval($times) + 1440000000;
        // echo "$userid,$times";
        return array($userid, $times);
    }
    /*
    * desc: 生成token
    *
    */
    function verifyToken($token)
    {
        list($userid, $times) = $this->deToken($token);
        $user = $this->getUser($userid, null, array('aggregated'=>true));
        // print_r($user);
        if($user){
            if($times == strtotime($user['lastime'])){
                // var_dump(true);
                return $userid;
            }
        }
        return false;
    }

    //token相关============================================end
};
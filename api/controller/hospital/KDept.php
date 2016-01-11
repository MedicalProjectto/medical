<?php
/**
 * 科室科室相关
 *
 *
 *
 *
*/
class KDept extends CControllerApi{
 
    /*
    * desc: 获取一个医院的所有部门
    * call: curl -d "hospitalid=1234" http://api.medical.me/hospital/dept/all?token=7e9afe9599bd8a9f4fcc6fe220b9652b
    *@mobile   --- 手机号
    *@idcard   --- 身份证号
    *
    */
    function actionAll()
    {
        $userid = $this->userid;//管理员的userid

        $hospitalid = $this->post('hospitalid');

        if($this->isPost()) {
            $postArr['enabled'] = 1;
            $MDept = $this->LoadApiModelMedical('dept');
            // print_r($user);
            $deptArr = $MDept->getDepts(null,array('hospitalid'=>$hospitalid),array('limit'=>300,'only_data'=>true));
            // print_r($retArr);
            if($deptArr){
                $this->response($deptArr, '添加科室成功');
            }
        }
        $this->error('添加科室失败');
    }
    /*
    * desc: 后台添加科室
    * call: curl -d "email=12345678901@qq.com&mobile=15298603026&idcard=123456789123456789&role=10" http://api.medical.me/dept/add?token=7e9afe9599bd8a9f4fcc6fe220b9652b
    *@mobile   --- 手机号
    *@idcard   --- 身份证号
    *
    */
    function actionAdd()
    {
        $userid = $this->userid;//管理员的userid

        $postArr = $this->posts('idcard,mobile,email,contact,status,sex,truename,plain,role,ethnic,duty,education,address,ll');

        if($this->isPost()) {
            $postArr['enabled'] = 1;
            $MDept = $this->LoadApiModelMedical('dept');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role   = intval($user['role']);
            // print_r($user);
            if(90 == $role){
                $retArr = $MDept->registerDept($postArr);
            }else{
                $this->error('无权限操作');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $dept = $retArr['dept'];
                $this->response($dept, '添加科室成功');
            }
        }
        $this->error('添加科室失败');
    }
 
    //删除科室
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $deptid=$this->post('deptid')){
            $MDept = $this->LoadApiModelMedical('dept');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 80){
                    $ok = $MDept->dropDept($deptid);
                    if($ok){
                        $this->response('科室已删除');
                    }
                }
            }
        }
        $this->error('无权限操作');
    }
    /*
    * desc: 更新和获取科室资料
    * call: curl http://api.medical.me/ucenter/user/profile?token=7e9afe9599bd8a9f4fcc6fe220b9652b [-XPOST -d "field=value"]
    *
    */
    function actionProfile()
    {
        //判断是否登入
        $userid = $this->userid;

        $MDept = $this->LoadApiModelMedical('user');
        if($this->isPost()){
            //更新profile
            $postArr = $this->posts('contact,logo,tel,provid,cityid,areaid,address,ll');
            $retArr = $MDept->updateUser($userid, $postArr);
            if(!$retArr['status']){
                $this->error('设置资料失败', 200);
            }
        }
        $info = $MDept->getUser($userid);
        if($info){
            $this->response($info, '获取资料成功');
        }
        $this->error('获取资料失败', 500);
        // print_r($info);
    }
};

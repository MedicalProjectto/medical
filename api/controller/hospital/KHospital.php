<?php
/**
 *
 *
 *
 *
 *
*/
class KHospital extends CControllerApi{
 
    
    /*
    * desc: 搜索医院(ajax)
    *
    */
    public function actionSearch()
    {
        $q     = trim($this->post('q'));
        $exArr = array('page'=>1,'limit'=>20);

        $whArr = array(
            'status>' => -1,
            'or'=>array(
                'title%'=>$q,
                'contact%'=>$q,
            ),
        );

        $MHospital = $this->LoadApiModelMedical('hospital');
        $dataArr = $MHospital->getHospitals(null, $whArr, $exArr);
        if(false !== $dataArr){
            $this->response($dataArr);
        }
        $this->error('没有数据');
    }
    /*
    * desc: 后台添加医院
    * call: curl -d "title=130&contact=c1&&mobile=15312345678&idcard=123456789123456788" http://api.medical.me/hospital/register?token=7a8833fc683d66021f4176740548a466
    *@mobile   --- 手机号
    *@idcard   --- 身份证号
    *
    */
    function actionRegister()
    {
        $userid = $this->userid;//管理员的userid

        $postArr = $this->posts('title,idcard,mobile,email,tel,deptname,contact,status,sex,truename,plain,role,height,weight,ethnic,duty,education,address,ll');
        $postArr['telphone'] = $postArr['tel'];

        if($this->isPost()) {
            $postArr['enabled'] = 1;
            $MHospital  = $this->LoadApiModelMedical('hospital');
            $MUser      = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role   = intval($user['role']);
            // print_r($user);
            if(90 == $role){
                $retArr = $MHospital->registerHospital($postArr);
            }else{
                $this->error('无权限操作');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $hospital = $retArr['hospital'];
                $user = $retArr['user'];
                $this->response(array('hospital'=>$hospital,'user'=>$user));
            }else{
                $message = isset($retArr['message'])?$retArr['message']:'添加医院失败';
                $this->error($message);
            }
        }
        $this->error('添加医院失败');
    }
 
    //删除医院
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $hospitalid=$this->post('hospitalid')){
            $MHospital = $this->LoadApiModelMedical('hospital');
            $MUser     = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if(90 == $role){
                    $ok = $MHospital->dropHospital($hospitalid);
                    if($ok){
                        $this->response('医院已删除');
                    }
                }
            }
        }
        $this->error('无权限操作');
    }
    /*
    * desc: 禁用/启用医院
    *
    * call: curl http://api.medical.me/hospital/status/enable?token=59a76a4e2036b33af0102da08118ee47
    * call: curl http://api.medical.me/hospital/status/disable?token=79cdd400a79ead22b0683aef60a94212 -d "hospitalid=120"
    *
    */
    function actionStatus()
    {
        $userid = $this->userid;//管理员的userid
        $otype  = $this->rest(0);

        if($hospitalid=$this->post('hospitalid')){
            $MHospital = $this->LoadApiModelMedical('hospital');
            $MUser     = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if(90 == $role){
                    if('enable' == $otype){
                        $ok = $MHospital->updateHospital($hospitalid, array('status'=>1));
                    }elseif('disable' == $otype){
                        $MProject = $this->LoadApiModelMedical('project');
                        $project = $MProject->getProject(null, array('hospitalid'=>$hospitalid,'status'=>0));
                        if($project){
                            $this->error('医院有活动项目，不能移除');
                        }
                        $eUser = $MUser->getUser(null,array('hospitalid'=>$hospitalid,'enabled>'=>-1));
                        if($eUser){
                            $this->error('医院有相关工作人员，不能移除');
                        }
                        $ok = $MHospital->updateHospital($hospitalid, array('status'=>-1));
                    }else{
                        $this->error('错误地址');
                    }
                    if(false !== $ok){
                        $this->response('操作完成');
                    }
                }
            }
        }
        $this->error('无权限操作');
    }
    /*
    * desc: 更新和获取医院资料
    * call: curl http://api.medical.me/hospital/detail?token=7e9afe9599bd8a9f4fcc6fe220b9652b [-XPOST -d "field=value"]
    *
    */
    function actionDetail()
    {
        //判断是否登入
        $userid = $this->userid;
        $hospitalid = $this->get('id', $this->post('id'));
        $MHospital = $this->LoadApiModelMedical('hospital');
        if($this->isPost()){
            //更新profile
            $postArr = $this->posts('title,contact,logo,mobile,tel,provid,cityid,areaid,address,ll');
            $postArr['telphone'] = $postArr['tel'];
            $retArr = $MHospital->updateHospital($hospitalid, $postArr);
            if(!$retArr['status']){
                $this->error('设置资料失败', 200);
            }
        }
        $hospital = $MHospital->getHospital($hospitalid);
        if($hospital){
            $this->response($hospital);
        }
        $this->error('没有找到医院');
        // print_r($info);
    }
};

<?php
/**
 * 项目的病人相关
 *
 *
 *
 *
*/
class KPatient extends CControllerApi{

    /*
    * desc: 获取项目的病人列表
    * call: curl "http://api.medical.me/project/patient/list?token=b5ad52a3b0129e1ff7b51a6f9e627777&projectid=20"
    *
    */
    function actionList()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 20);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
        );

        if($projectid = $this->get('projectid')) {
            $whArr = array(
                'doctorid'   => $this->get('doctorid'),
                'projectid'  => $projectid,
                'hospitalid' => $this->get('hospitalid'),
                'username%'  => $this->get('username'),
            );
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MProject = $this->LoadApiModelMedical('project');
            $patientArr = $MProject->getAppends('patient',$whArr,$exArr);
            // print_r($patientArr);
            if($patientArr){
                $this->response($patientArr);
            }
        }
        $this->error('没有找到任何新病人信息');
    }
    /*
    * desc: 为项目添加病人
    * call: curl -d "projectid=10&patientid[]=30&num_goal=2" http://api.medical.me/project/patient/append?token=59a76a4e2036b33af0102da08118ee47
    *       curl -d "projectid=98&mobile=15298603024&idcard=223456789123456788&username=pat3&deptname=kkkkk" http://api.medical.me/project/patient/append?token=b621fdedcb43146efa71c613429bfe50
    *
    */
    function actionAppend()
    {
        $userid = $this->userid;//医生的userid

        if($this->isPost() && $projectid=$this->post('projectid')) {
            $postArr = $this->posts('patientid');     //病人id序列
            $othArr  = $this->posts('num_goal');

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            $hospitalid = $user['hospitalid'];
            if($role >= 10){
                if($role >= 20){
                    $doctorid = $this->post('doctorid');
                    $doctorid = $doctorid?$doctorid:$userid; //相当于操作帮别人(doctorid)添加
                }else{
                    $doctorid = $userid;
                }
                if(($mobile=$this->post('mobile')) && ($idcard=$this->post('idcard'))){
                    //直接添加
                    $userinfo = array(
                        'mobile'     => $mobile,
                        'idcard'     => $idcard,
                        'doctorid'   => $this->post('doctorid',$userid),
                        'role'       => -10,
                        'hospitalid' => $hospitalid,
                        'username'   => $this->post('username'),
                        'plain'      => substr($mobile, -6),
                    );
                    $userinfo = array_merge($userinfo, $this->posts('email,deptid,truename,sex,ethnic,birthplace,address,idcard,age,height,weight,tel,num_ill,date_in,date_out,deptname,avatar'));
                    CFun::removeArrayNull($userinfo, true);
                    // print_r($userinfo);exit;
                    $retArr = $MUser->addUser($userinfo);
                    // print_r($retArr);exit;
                    if(1 != $retArr['status']){
                        $this->error('添加病人信息时失败:'.$retArr['message']);
                    }
                    $patientid = $retArr['user']['id'];
                    $postArr['patientid'] = array($patientid);
                }
                $ok = $MProject->appendPatient($projectid, $doctorid, $postArr);
            }else{
                $this->error('操作无权限');
            }
            if(false !== $ok){
                $this->response($postArr);
            }else{
                $this->error('为项目添加病人失败或已添加过');
            }
        }
        $this->error('添加病人失败');
    }
    /*
    * desc: 从项目移除病人
    * call: curl -d "projectid=28&doctorid=28&patientid=37" http://api.medical.me/project/patient/remove?token=e9b75c67f3154fed13a4f98e7e50b0e1
    *
    */
    function actionRemove()
    {
        $userid = $doctorid = $this->userid;//医生的userid

        if($projectid=$this->post('projectid')) {
            $patientid = $this->post('patientid');     //病人id序列
            // print_r($patientid);exit;

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            $hospitalid = $user['hospitalid'];
            if($role >= 10){
                if($role >= 80){
                    $doctorid = $this->post('doctorid', $userid); //相当于操作帮别人(doctorid)移除
                }
                $ok = $MProject->removePatient($projectid, $doctorid, $patientid);
            }else{
                $this->error('操作无权限');
            }
            if(false !== $ok){
                $this->message('成功移除');
            }
        }
        $this->error('移除病人失败');
    }
    /*
    * desc: 移动病人至其它医生下
    * call: curl -d "projectid=98&doctorid_old=92&doctorid_new=163&patientid=215" http://api.medical.me/project/patient/move?token=b658efc03bbf5e9d8195c18bf2619ca8
    * call: curl -d "projectid=98&doctorid_old=92&doctorid_new=163&patientid=215" http://115.29.176.160/project/patient/move?token=e9b75c67f3154fed13a4f98e7e50b0e1
    *
    */
    function actionMove()
    {
        $userid = $doctorid = $this->userid;//医生的userid

        if(($projectid=$this->post('projectid')) && ($doctorid_new = $this->post('doctorid_new'))) {
            $doctorid_old = $this->post('doctorid_old', $userid, true); //原医生id
            $patientid = $this->post('patientid');    //病人id
            // print_r($patientid);exit;

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            if($role >= 20){
                $ok = $MProject->movePatient($projectid, $patientid, $doctorid_new, $doctorid_old);
                if(false !== $ok){
                    $this->message('移动成功');
                }
            }else{
                $this->error('操作无权限');
            }
        }
        $this->error('移动病人失败');
    }
 
    /*
    * desc: 删除一个病人
    * call: curl -d "id=3" http://api.medical.me/project/patient/drop?token=386b710e50c91f9b056a1218a30078fe
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $id=$this->post('id')){
            $MProject = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 10){
                    $ok = $MProject->dropAppended($id,'patient');
                    if($ok){
                        $this->message('病人已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
};

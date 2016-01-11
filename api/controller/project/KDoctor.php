<?php
/**
 * 项目的医生相关
 *
 *
 *
 *
*/
class KDoctor extends CControllerApi{

    /*
    * desc: 获取项目的医生列表
    * call: curl "http://api.medical.me/project/doctor/list?token=b5ad52a3b0129e1ff7b51a6f9e627777&projectid=20"
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
                'piid'       => $this->get('piid'),
                'projectid'  => $projectid,
                'hospitalid' => $this->get('hospitalid'),
                'username%'  => $this->get('username'),
            );
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MProject = $this->LoadApiModelMedical('project');
            $patientArr = $MProject->getAppends('doctor',$whArr,$exArr);
            // print_r($patientArr);
            if(false !== $patientArr){
                if(!empty($patientArr['data'])){
                    foreach($patientArr['data'] as &$r0001){
                        $r0001['num_patient'] = $MProject->getCounts('patient',array('projectid'=>$r0001['projectid'],'doctorid'=>$r0001['doctorid']));
                    }
                }
                $this->response($patientArr);
            }
        }
        $this->error('没有找到任何新医生信息');
    }
    /*
    * desc: 为项目添加医生
    * call: curl -d "projectid=10&doctorid[]=29&num_goal=2" http://api.medical.me/project/doctor/append?token=3d8d0bf9bee3340fb2930f430b0bdbb3
    *
    */
    function actionAppend()
    {
        $userid = $this->userid;//PI的userid

        if($this->isPost() && $projectid=$this->post('projectid')) {
            $postArr = $this->posts('doctorid');     //医生id序列
            $othArr  = $this->posts('num_goal');

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);

            if($role >= 20){
                if($role >= 80){
                    $piid = $this->post('piid');
                    $piid = $piid?$piid:$userid; //相当于操作帮别人(piid)添加
                }else{
                    $piid = $userid;
                }
                $ok = $MProject->appendDoctor($projectid, $piid, $postArr, $othArr);
            }else{
                $this->error('操作无权限');
            }
            if($ok){
                $this->message('添加成功');
            }
        }
        $this->error('添加项目失败');
    }

    /*
    * desc: 从项目移除医生
    * call: curl -d "projectid=98&piid=161&doctorid=92" http://api.medical.me/project/doctor/remove?token=b658efc03bbf5e9d8195c18bf2619ca8
    * call: curl -d "projectid=98&piid=161&doctorid=92" http://115.29.176.160/project/doctor/remove?token=072ca900c4a323f72e4ebc7717e158b1
    *
    */
    function actionRemove()
    {
        $userid = $piid = $this->userid;//医生的userid

        if($projectid=$this->post('projectid')) {
            $doctorid = $this->post('doctorid');     //医生id序列
            // print_r($doctorid);exit;

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            $hospitalid = $user['hospitalid'];
            if($role >= 10){
                if($role >= 80){
                    $piid = $this->post('piid', $userid); //相当于操作帮别人(piid)移除
                }
                $ok = $MProject->removeDoctor($projectid, $piid, $doctorid);
            }else{
                $this->error('操作无权限');
            }
            if(false !== $ok){
                $this->message('成功移除');
            }
        }
        $this->error('移除医生失败');
    }
 
    /*
    * desc: 删除一个医生
    * call: curl -d "id=3" http://api.medical.me/project/doctor/drop?token=386b710e50c91f9b056a1218a30078fe
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
                if($role >= 20){
                    $ok = $MProject->dropAppended($id,'doctor');
                    if($ok){
                        $this->message('医生已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
};

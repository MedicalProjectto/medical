<?php
/**
 * 项目的PI相关
 *
 *
 *
 *
*/
class KPi extends CControllerApi{

    /*
    * desc: 获取项目的PI列表
    * call: curl "http://api.medical.me/project/pi/list?token=b5ad52a3b0129e1ff7b51a6f9e627777&projectid=20"
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
                'projectid'  => $projectid,
                'hospitalid' => $this->get('hospitalid'),
                'username%'  => $this->get('username'),
            );
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MProject = $this->LoadApiModelMedical('project');
            $patientArr = $MProject->getAppends('pi',$whArr,$exArr);
            // print_r($patientArr);
            if($patientArr){
                $this->response($patientArr);
            }
        }
        $this->error('没有找到任何新PI信息');
    }
    /*
    * desc: 为项目添加PI
    * call: curl -d "projectid=98&piid[]=238&num_goal=140" http://api.medical.me/project/pi/append?token=72088aa316e3b14a474efd0f11dc6ba9
    * curl -d "projectid=98&piid[]=238&num_goal=140" http://115.29.176.160/project/pi/append?token=72088aa316e3b14a474efd0f11dc6ba9
    *
    */
    function actionAppend()
    {
        $userid = $this->userid;//PI的userid

        if($this->isPost() && $projectid=$this->post('projectid')) {
            $postArr = $this->posts('piid,num_goal');     //PIid序列
            $othArr  = $this->posts('num_goal');

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);

            if($role >= 20){
                $ok = $MProject->appendPI($projectid, $postArr, $othArr);
            }else{
                $this->error('操作无权限');
            }
            if($ok){
                $this->message('编辑成功');
            }else{
                $this->error('编辑失败');
            }
        }
        $this->error('不能添加PI');
    }

    /*
    * desc: 从项目移除pi
    * call: curl -d "projectid=103&piid=160" http://api.medical.me/project/pi/remove?token=e9b75c67f3154fed13a4f98e7e50b0e1
    *
    */
    function actionRemove()
    {
        $userid = $this->userid;//医生的userid

        if($projectid=$this->post('projectid')) {
            $piid = $this->post('piid');     //医生id序列
            // print_r($piid);exit;

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            if($userid == $piid){
                $this->error('自己不能删除自己');
            }
            if($role >= 20){
                $ok = $MProject->removePI($projectid, $userid, $piid);
            }else{
                $this->error('操作无权限');
            }
            if(false !== $ok){
                $this->message('成功移除');
            }
        }
        $this->error('移除pi失败');
    }
 
    /*
    * desc: 删除一个PI
    * call: curl -d "id=4" http://api.medical.me/project/pi/drop?token=386b710e50c91f9b056a1218a30078fe
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
                    $ok = $MProject->dropAppended($id,'pi');
                    if($ok){
                        $this->message('PI已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
};

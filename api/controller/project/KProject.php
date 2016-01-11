<?php
/**
 * 项目相关
 *
 *
 *
 *
*/
class KProject extends CControllerApi{

    /*
    * desc: 获取项目列表
    * call: curl "http://api.medical.me/project/list?token=b5ad52a3b0129e1ff7b51a6f9e627777&status=1"
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

        if(1) {
            $whArr = array(
                'hospitalid' => $this->get('hospitalid'),
                'userid'     => $this->get('userid'),
                'status'     => $this->get('status'),
            );
            CFun::removeArrayNull($whArr);

            $MProject = $this->LoadApiModelMedical('project');
            $projectArr = $MProject->getProjects(null,$whArr,$exArr);
            // $this->dump($project);
            if($projectArr){
                $this->response($projectArr);
            }
        }
        $this->error('没有找到任何项目');
    }
    /*
    * desc: 获取当前用户参与的项目
    * call: curl "http://api.medical.me/project/joined?token=199fc2f426eec89bd9c4fcffbdbf466e" -d ""
    * call: curl "http://115.29.176.160/project/joined?token=199fc2f426eec89bd9c4fcffbdbf466e"
    *
    */
    function actionJoined()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 100);

        if(1) {
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);

            $whArr = array(
                // 'hospitalid' => $user['hospitalid'],
                // 'userid'     => $this->get('userid'),
                'status'     => $this->get('status',0,true),
            );
            //CFun::removeArrayNull($whArr);

            $MProject = $this->LoadApiModelMedical('project');
            if(80 == $role){
                $exArr  = array(
                    'page'  => $page,
                    'limit' => $limit,
                    'only_data' => true,
                );
                $ap_ex_arr  = array(
                    'page'  => 1,
                    'limit' => 1000,
                    'only_data' => true,
                    'aggregated' => true,
                    'fields' => 'distinct projectid',
                );
                $hosid = $user['hospitalid'];
                $appendArr1 = $MProject->getAppends('pi',array('hospitalid'=>$hosid),$ap_ex_arr);
                $appendArr2 = $MProject->getAppends('doctor',array('hospitalid'=>$hosid),$ap_ex_arr);
                $appendArr3 = $MProject->getAppends('patient',array('hospitalid'=>$hosid),$ap_ex_arr);
                
                $appendArr = array_merge($appendArr1, $appendArr2, $appendArr3);
                $projid_arr = $this->getArrayColumn($appendArr, 'projectid');
                $projid_arr = array_unique($projid_arr);
                

                $projectArr = $MProject->getProjects($projid_arr,$whArr,$exArr);
                // print_r($projectArr);
            }else{
                $typeArr = array(10=>'doctor', 20=>'pi', -10=>'patient');
                if(!isset($typeArr[$role])){
                    $this->error('角色不允许');
                }
                $type = $typeArr[$role];
                $uidfield = $type.'id'; //piid,doctorid,patientid
                $exArr = array(
                    'limit' => 100,
                    'only_data' => true,
                    'fields'=>'distinct projectid',
                    'keyas'=>'projectid'
                );
                $appendArr = $MProject->getAppends($type,array($uidfield=>$userid),$exArr);
                $pid_arr = array_keys($appendArr);
                // print_r($pid_arr);
                // print_r($whArr);
                $projectArr = $MProject->getProjects($pid_arr,$whArr,$exArr);
                // print_r($projectArr);
                // exit;
            }
            
            // $this->dump($project);
            if($projectArr){
                $this->response($projectArr);
            }
        }
        $this->error('没有找到任何项目');
    }

    /*
    * desc: 获取一个项目的详情
    * call: curl "http://api.medical.me/project/detail?token=b5ad52a3b0129e1ff7b51a6f9e627777&id=20" -d ""
    *
    */
    function actionDetail()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost()){

        }

        if($projectid = $this->get('id')) {
            $MProject = $this->LoadApiModelMedical('project');
            $project  = $MProject->getDetail($projectid,null,array('join_pi'=>true,'join_tpl'=>true,'join_doctor'=>true,'join_patient'=>true,'join_tpl'=>true,'join_creator'=>true));
            // $this->dump($project);
            if($project){
                $this->response($project);
            }
        }
        $this->error('获取项目失败');
    }
    /*
    * desc: 创建项目
    * call: curl -d "name=project3&num_goal=88&opendate=2016-1-1&closedate=2016-12-1&remark=tt&piid[]=14&piid[]=15&patientid[]=13&doctorid[]=16&tplid[]=2" http://api.medical.me/project/create?token=d9db77774f618fdf7e74df96fc78d40d
    *
    */
    function actionCreate()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost()) {
            $postArr = $this->posts('name,opendate,closedate,num_goal,my_goal,remark');
            $postArr['makerid'] = $userid; //创建者
            $pis      = $this->posts('piid');          //PI id序列
            $doctors  = $this->posts('piid,doctorid'); //医生id序列
            $patients = $this->posts('doctorid,patientid');     //病人id序列
            $tpls     = $this->posts('tplid');         //模板id序列
            if(empty($pis['piid'])){
                $pis['piid'] = array($userid); //默认为当前用户
            }
            // print_r($pis);exit;

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);

            $postArr['hospitalid'] = $user['hospitalid'];//创建者的医院
            $postArr['userid']     = $userid;
            
            if($role >= 20){
                $retArr = $MProject->addProject($postArr, $pis, $doctors, $patients, $tpls);
            }else{
                $this->error('操作无权限');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $project = $retArr['project'];
                $this->response($project);
            }
        }
        $this->error('添加项目失败');
    }
 
    //删除项目
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $projectid=$this->post('projectid')){
            $MProject = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 80){
                    $ok = $MProject->dropProject($projectid);
                    if($ok){
                        $this->response('项目已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
    /*
    * desc: 项目归档
    * call: curl -d "projectid=22" http://api.medical.me/project/archive?token=0f5e4d24328d2a3a7cd7d5610985cc56
    *
    */
    function actionArchive()
    {
        $userid = $this->userid;//管理员的userid

        if($projectid=$this->post('projectid')){
            $MProject = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 20){
                    $ok = $MProject->updateProject($projectid,array('status'=>1));
                    if($ok){
                        $this->response('项目已归档');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
    /*
    * desc: 项目归档
    * call: curl -d "projectid=20" http://api.medical.me/project/active?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionActive()
    {
        $userid = $this->userid;//管理员的userid

        if($projectid=$this->post('projectid')){
            $MProject = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 20){
                    $ok = $MProject->updateProject($projectid,array('status'=>0));
                    if($ok){
                        $this->response('项目已归档');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
    /*
    * desc: 更新项目/值
    * call: curl -d "projectid=20&remark=tt2&piid[]=14&piid[]=15" http://api.medical.me/project/change?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionChange()
    {
        //判断是否登入
        $userid = $this->userid;

        $MProject = $this->LoadApiModelMedical('project');
        if($this->isPost() && $projectid = $this->post('projectid')){
            //更新profile
            $postArr  = $this->posts('name,opendate,closedate,num_goal,remark');
            
            $pis      = $this->posts('piid');          //PI id序列
            $doctors  = $this->posts('piid,doctorid'); //医生id序列
            $patients = $this->posts('doctorid,patientid');     //病人id序列
            $tpls     = $this->posts('tplid');         //模板id序列

            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $hospitalid = $user['hospitalid'];

            $old = $MProject->getProject($projectid); //修改前的项目
            if(!$old || $hospitalid != $old['hospitalid']){
                $this->error('操作无权限', 403);
            }
            $retArr = $MProject->updateProject($projectid, $postArr, $pis, $doctors, $patients, $tpls);
            if(!$retArr['status']){
                $this->error('设置资料失败', 500);
            }else{
                $this->response($retArr['project']);
            }
        }
        $this->error('设置资料失败', 500);
        // print_r($info);
    }
};

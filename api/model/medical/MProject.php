<?php
/**
 * desc: 项目相关
 *
 *
 *
*/

class MProject extends CHookModel {

    private $tProject  = 'project';
    private $tPPi      = 'project_pi';
    private $tPDoctor  = 'project_doctor';
    private $tPPatient = 'project_patient';
    private $tPTpl     = 'project_tpl';

    /*
    * desc: 获取多条项目记录(加了个s是为了区分project)
    *
    *
    */
    public function getProjects($projectids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $projectids){
            if(is_array($projectids)){
                $whArr = array_merge(array('id in'=>$projectids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$projectids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated   = isset($exArr['aggregated'])?$exArr['aggregated']:false;
        $join_pi      = isset($exArr['join_pi'])?$exArr['join_pi']:false;
        $join_doctor  = isset($exArr['join_doctor'])?$exArr['join_doctor']:false;
        $join_patient = isset($exArr['join_patient'])?$exArr['join_patient']:false;
        $join_tpl     = isset($exArr['join_tpl'])?$exArr['join_tpl']:false;
        $join_creator = isset($exArr['join_creator'])?$exArr['join_creator']:false;//创建人
        if(!$aggregated){
            if($join_pi){
                $exArr['join']['project_pi']      = "id:projectid";
            }
            if($join_doctor){
                $exArr['join']['project_doctor']  = "id:projectid";
            }
            if($join_patient){
                $exArr['join']['project_patient'] = "id:projectid";
            }
            if($join_tpl){
                $exArr['join']['project_tpl']     = "id:projectid";
            }
        }
        /***************************join end********************/
        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tProject, $whArr, $exArr);
        if(!$dataArr)return false;
        //业务处理...
        if(!$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_creator){ //创建者
                $MUser = $this->LoadApiModelMedical('user');
                $uid_arr = $this->getArrayColumn($rowArr,'userid');
                
                $userArr = $MUser->getUsers($uid_arr, null, array('limit'=>count($uid_arr),'only_data'=>true, 'fields'=>'^ctime,utime,code,validated,lastime'));
                $rowArr  = $this->joinToArray($rowArr, $userArr,'userid:id','creator');
                // print_r($rowArr);exit;
            }
        }
        return $dataArr;
    }
    /*
    * desc: 获取一条项目记录
    *
    *
    */
    public function getProject($projectid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $projectid){
            $whArr = array_merge(array('id'=>$projectid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getProjects(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }
    /*
    * desc: 获取一条项目详细情况
    *
    *
    */
    public function getDetail($projectid=null, $whArr=array(), $exArr=array())
    {
        $project = $this->getProject($projectid, $whArr, $exArr);
        if($project){
            $_user_fields = 'id,username,idcard,mobile,age,sex,ethnic,birthplace,address,avatar';
            if(isset($project['project_pi'])){
                $MUser = $this->LoadApiModelMedical('user');
                $piArr = &$project['project_pi'];
                $piid_arr = $this->getArrayColumn($piArr, 'piid');
                $userArr  = $MUser->getUsers($piid_arr, null, array('only_data'=>true,'limit'=>count($piid_arr),/*'keyas'=>'id',*/'fields'=>$_user_fields));
                $project['pi'] = $userArr;
            }
            if(isset($project['project_doctor'])){
                $MUser = $this->LoadApiModelMedical('user');
                $docArr = &$project['project_doctor'];
                $docid_arr = $this->getArrayColumn($docArr, 'doctorid');
                $userArr  = $MUser->getUsers($docid_arr, null, array('only_data'=>true,'limit'=>count($docid_arr),/*'keyas'=>'id',*/'fields'=>$_user_fields));
                $project['doctor'] = $userArr;
            }
            if(isset($project['project_patient'])){
                $MUser = $this->LoadApiModelMedical('user');
                $patArr = &$project['project_patient'];
                $patid_arr = $this->getArrayColumn($patArr, 'patientid');
                $userArr  = $MUser->getUsers($patid_arr, null, array('only_data'=>true,'limit'=>count($patid_arr),/*'keyas'=>'id',*/'fields'=>$_user_fields));
                $project['patient'] = $userArr;
            }
            if(isset($project['project_tpl'])){
                $MTpl   = $this->LoadApiModelMedical('tpl');
                $tplArr = &$project['project_tpl'];
                $tplid_arr = $this->getArrayColumn($tplArr, 'tplid');
                $tplArr    = $MTpl->getTpls($tplid_arr, null, array('only_data'=>true,'limit'=>count($tplid_arr),/*'keyas'=>'id'*/));
                $project['tpl'] = $tplArr;
            }
            if(isset($project['project_pi'])){ //医院
                $MHospital = $this->LoadApiModelMedical('hospital');
                $piArr     = &$project['project_pi'];
                $hid_arr   = $this->getArrayColumn($piArr, 'hospitalid');
                $hospitalArr = $MHospital->getHospitals($hid_arr, null, array('only_data'=>true,'limit'=>count($hid_arr),/*'keyas'=>'id',*/'fields'=>'id,title,adminid,contact,telphone,mobile'));
                $project['hospital'] = $hospitalArr;
            }
        }
        // print_r($project);
        return $project;
    }

    
    
    /*
    * desc: 添加一个项目
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               project --- 项目信息
    *               )
    */
    public function addProject($postArr, $piArr=array(), $doctorArr=array(), $patientArr=array(), $tplArr=array())
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'project'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['userid'])) {
            $retArr['message'] = '没有用户';
            return $retArr;
        }
        if(empty($postArr['name'])) {
            $retArr['message'] = '项目名称不能为空';
            return $retArr;
        }
        $postArr['ctime'] = date("Y-m-d H:i:s");
        //数据检查==================================end

        $MTpl = $this->LoadApiModelMedical('tpl');
        $tplArr = $MTpl->getTpls(null,null,array('limit'=>100,'only_data'=>true));
        $tplArr = $this->getArrayColumn($tplArr, 'id');
        $tplArr = array('tplid'=>$tplArr);

        $othArr = array();
        if(!empty($postArr['my_goal'])){
            $othArr['num_goal'] = $postArr['my_goal'];
        }

        $old = $this->getProject(null,array('hospitalid'=>$postArr['hospitalid'],'name'=>$postArr['name']));
        if($old){
            // return $this->updateProject($old['id'], array('hits'=>$new_hits));
            
            $this->appendPI($old['id'], $piArr, $othArr);
            $this->appendDoctor($old['id'], $postArr['userid'], $doctorArr);
            $this->appendPatient($old['id'], $postArr['userid'], $patientArr);
            $this->appendTpl($old['id'], $tplArr);

            $retArr['project'] = $old;
            $retArr['message'] = '项目已存在';
            return $retArr;
        }
        
        $id = $this->addAtom($this->tProject, $postArr, array('log'=>'project'));
        if($id){
            $this->appendPI($id, $piArr, $othArr);
            $this->appendDoctor($id, $postArr['userid'], $doctorArr);
            $this->appendPatient($id, $postArr['userid'], $patientArr);
            $this->appendTpl($id, $tplArr);

            $project = $this->getProject($id);
            $retArr['status']  = 1;
            $retArr['project'] = $project;
            $retArr['message'] = "添加项目成功";
        }else{
            // $retArr['message'] = null;
        }
        return $retArr;
    }
    /*
    * desc: 为一项目添PI
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function appendPI($projectid, $piArr, $othArr=array(), $overwrite=false)
    {
        $logfile = "MProject";
        if(empty($piArr)){
            CLog::WriteLog(array('msg'=>'pi参数不完整', 'piArr'=>$piArr), $logfile);
            return false;
        }
        $project = $this->getProject($projectid);
        if(!$project){
            CLog::WriteLog(array('msg'=>'项目不存在', 'project'=>$project), $logfile);
            return false;
        }

        $MUser = $this->LoadApiModelMedical('user');
        // print_r($piArr);
        $piid_arr = $piArr['piid'];
        $userArr = $MUser->getUsers($piid_arr, array('role'=>20), array('limit'=>count($piid_arr),'only_data'=>true));
        if(empty($userArr)){
            CLog::WriteLog(array('msg'=>'用户不存在', 'piid_arr'=>$piid_arr, 'userArr'=>$userArr), $logfile);
            return false;
        }

        $addArr = array(); //准备要加的pi记录(多条)
        $_mkid  = $project['userid'];
        foreach($userArr as $user){
            $makerid = $_mkid==$user['id']?$_mkid:0;
            $row  = array(
                'makerid'     => $makerid,
                'piid'        => $user['id'],
                'hospitalid'  => $user['hospitalid'],
                'projectid'   => $projectid,
                'username'    => $user['username'],
                'ctime'       => date("Y-m-d H:i:s"),
            );
            if(!empty($othArr['num_goal'])){
                $row['num_goal'] = $othArr['num_goal'];
            }
            $addArr[] = $row;
        }
        if($overwrite){
            $this->deleteData($this->tPPi, array('projectid'=>$projectid), 1000);
        }
        $oldPIs = $this->getAppends('pi',array('projectid'=>$projectid),array('no_hospital'=>1,'only_data'=>true,'limit'=>1000));
        // $ok = $this->addMore($this->tPPi, $addArr, array('ignored'=>true,'replaced'=>true));
        CLog::WriteLog(array('msg'=>'执行replaceData', 'addArr'=>$addArr), $logfile);
        $ok = $this->replaceData($this->tPPi, $addArr, 'projectid,piid');

        //验证pi数量
        $num_goal_proj = $project['num_goal'];
        $projPIs = $this->getAppends('pi',array('projectid'=>$projectid),array('no_hospital'=>1,'only_data'=>true,'limit'=>1000)); //pi的所有pi信息,主要是查询num_goal
        $num_goal_arr = $this->getArrayColumn($projPIs, 'num_goal');
        $num_goal_of_pis = array_sum($num_goal_arr);
        if($num_goal_of_pis > $num_goal_proj){
            foreach($addArr as $wh){//把原的一行作为条件去删除
                $this->deleteData($this->tPPi, $wh);
            }
            if($oldPIs){
                $this->replaceData($this->tPPi, $oldPIs, 'id'); //还原
            }
            CLog::WriteLog(array('msg'=>'目标数量不存在', 'num_goal_proj'=>$num_goal_proj, 'num_goal_of_pis'=>$num_goal_of_pis), $logfile);
            $ok = false;
        }
        //end 验证pi数量

        //获取项目pi总数
        $num_pi = $this->getCount($this->tPPi, array('projectid'=>$projectid));
        if($num_pi > 0){
            $this->updateProject($projectid, array('num_pi'=>$num_pi));
        }
        //end 获取项目pi个数
        return $ok;
    }
    /*
    * desc: 为一项目添医生(PI有权限)
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function appendDoctor($projectid, $piid, $doctorArr, $othArr=array(), $overwrite=false)
    {
        $logfile = 'MProject';
        if(empty($doctorArr)){
            CLog::WriteLog(array('msg'=>'参数不完整', 'doctorArr'=>$doctorArr), $logfile);
            return false;
        }
        $project = $this->getProject($projectid);
        if(!$project){
            CLog::WriteLog(array('msg'=>'项目不存在', 'projectid'=>$projectid), $logfile);
            return false;
        }

        $MUser = $this->LoadApiModelMedical('user');
        $docArr = $this->fieldAsKey(CUtil::formArrayFormatting($doctorArr,0),'doctorid');
        $docid_arr = $doctorArr['doctorid'];
        $userArr = $MUser->getUsers($docid_arr, array('role'=>10), array('limit'=>count($docid_arr),'only_data'=>true));
        if(empty($userArr)){
            CLog::WriteLog(array('msg'=>'用户不存在', 'docid_arr'=>$docid_arr), $logfile);
            return false;
        }

        $addArr = array(); //准备要加的医生记录(多条)
        foreach($userArr as $user){
            $doctorid = $user['id'];
            if(!isset($docArr[$doctorid]))continue;
            $row = array(
                'piid'        => isset($docArr[$doctorid]['piid'])?$docArr[$doctorid]['piid']:$piid,
                'doctorid'    => $user['id'],
                'projectid'   => $projectid,
                'hospitalid'  => $user['hospitalid'],
                'username'    => $user['username'],
                'ctime'       => date("Y-m-d H:i:s"),
            );
            if(!empty($othArr['num_goal'])){
                $row['num_goal'] = $othArr['num_goal'];
            }
            $addArr[] = $row;
        }
        
        $oldDocs = $this->getAppends('doctor',array('projectid'=>$projectid,'piid'=>$piid),array('no_hospital'=>1,'only_data'=>true,'limit'=>1000));
        // print_r($addArr);
        if($overwrite){
            $this->deleteData($this->tPDoctor, array('projectid'=>$projectid), 1000);
        }
        // $ok = $this->addMore($this->tPDoctor, $addArr, array('ignored'=>true,'replaced'=>true));
        CLog::WriteLog(array('msg'=>'执行replaceData', 'addArr'=>$addArr), $logfile);
        $ok = $this->replaceData($this->tPDoctor, $addArr, 'projectid,doctorid');

        //验证医生数量
        $piAppend = $this->getAppends('pi',array('projectid'=>$projectid,'piid'=>$piid),array('no_hospital'=>1,'only_data'=>true)); //pi信息,主要是查询num_goal
        if(!isset($piAppend[0])) {
            CLog::WriteLog(array('msg'=>'piAppend信息不存在', 'piAppend'=>$piAppend), $logfile);
            return false;
        }
        $piAppend = $piAppend[0];
        $num_goal_pi = $piAppend['num_goal'];
        $piDoctors = $this->getAppends('doctor',array('projectid'=>$projectid,'piid'=>$piid),array('no_hospital'=>1,'only_data'=>true,'limit'=>1000)); //pi的所有医生信息,主要是查询num_goal
        $num_goal_arr = $this->getArrayColumn($piDoctors, 'num_goal');
        $num_goal_of_docs = array_sum($num_goal_arr);
        if($num_goal_of_docs > $num_goal_pi){
            foreach($addArr as $wh){//把原的一行作为条件去删除
                $this->deleteData($this->tPDoctor, $wh);
            }
            if($oldDocs){
                $this->replaceData($this->tPDoctor, $oldDocs, 'id'); //还原
            }
            CLog::WriteLog(array('msg'=>'num_goal数量过大', 'num_goal_of_docs'=>$num_goal_pi), $logfile);
            $ok = false;
        }
        //end 验证医生数量

        //获取项目下医生的个数
        $num_doctor = $this->getCount($this->tPDoctor, array('projectid'=>$projectid));
        $this->updateProject($projectid, array('num_doctor'=>$num_doctor));
        //end 获取项目下医生的个数

        //获取pi下的医生数
        $num_doctor = $this->getCount($this->tPDoctor, array('projectid'=>$projectid,'piid'=>$piid));
        $this->updateData($this->tPPi, array('num_doctor'=>$num_doctor), array('projectid'=>$projectid,'piid'=>$piid));
        //end 获取pi下的医生数

        //end 获取病人的个数
        return $ok;
    }
    /*
    * desc: 为一项目添病人(医生有权限)
    *       同时更新项目的病人数(num_joined)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function appendPatient($projectid, $doctorid, $patientArr, $overwrite=false)
    {
        $logfile = 'MProject';
        if(empty($patientArr)){
            CLog::WriteLog(array('msg'=>'参数不完整', 'patientArr'=>$patientArr), $logfile);
            return false;
        }
        $project = $this->getProject($projectid);
        if(!$project){
            CLog::WriteLog(array('msg'=>'项目不存在', 'projectid'=>$projectid), $logfile);
            return false;
        }

        $MUser = $this->LoadApiModelMedical('user');
        // print_r($patientArr);
        $patid_arr = $patientArr['patientid'];
        if(empty($patid_arr)){
            CLog::WriteLog(array('msg'=>'没有病人', 'patid_arr'=>$patid_arr), $logfile);
            return false;
        }
        $userArr = $MUser->getUsers($patid_arr, array('role'=>-10), array('limit'=>count($patid_arr),'only_data'=>true));
        if(empty($userArr)){
            CLog::WriteLog(array('msg'=>'用户不存在', 'patid_arr'=>$patid_arr), $logfile);
            return false;
        }

        $doctor = $MUser->getUser($doctorid);
        if(!$doctor) {
            CLog::WriteLog(array('msg'=>'医生user不存在', 'patid_arr'=>$patid_arr), $logfile);
            return false;
        }

        $addArr = array(); //准备要加的病人记录(多条)
        foreach($userArr as $user){
            $row = array(
                'doctorid'    => $doctorid,
                'patientid'   => $user['id'],
                'projectid'   => $projectid,
                'hospitalid'  => $user['hospitalid'],
                'username'    => $user['username'],
                'ctime'       => date("Y-m-d H:i:s"),
            );
            $addArr[] = $row;
        }
        $oldPats = $this->getAppends('patient',array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid'],'doctorid'=>$doctorid),array('only_data'=>true,'limit'=>1000));
        if($overwrite){
            $this->deleteData($this->tPPatient, array('projectid'=>$projectid), 10000);
        }
        // $ok = $this->addMore($this->tPPatient, $addArr, array('ignored'=>true,'replaced'=>true));
        $ok = $this->replaceData($this->tPPatient, $addArr, 'projectid,patientid');

        //验证病人数量
        $docAppend = $this->getAppends('doctor',array('projectid'=>$projectid,'doctorid'=>$doctorid),array('no_hospital'=>1,'only_data'=>true)); //pi信息,主要是查询num_goal
        if(!isset($docAppend[0])) {
            CLog::WriteLog(array('msg'=>'docAppend不存在', 'docAppend'=>$docAppend), $logfile);
            return $ok;
        }
        $docAppend = $docAppend[0];
        $num_goal_doc = $docAppend['num_goal'];
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'doctorid'=>$doctorid));
        if($num_patient > $num_goal_doc){
            foreach($addArr as $wh){//把原的一行作为条件去删除
                $this->deleteData($this->tPPatient, $wh);
            }
            if($oldPats){
                $this->replaceData($this->tPPatient, $oldPats, 'id'); //还原
            }
            CLog::WriteLog(array('msg'=>'num_goal过大', 'num_patient'=>$num_patient,'num_goal_doc'=>$num_goal_doc), $logfile);
            $ok = false;
        }
        //end 验证病人数量

        //获取项目下病人的个数
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid));
        $this->updateProject($projectid, array('num_patient'=>$num_patient));
        //end 获取项目下病人的个数

        //获取医生下的病人数
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'doctorid'=>$doctorid));
        $this->updateData($this->tPDoctor, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'doctorid'=>$doctorid));
        //end 获取医生的个数

        //获取医院下(pi)的病人数
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid']));
        $this->updateData($this->tPPi, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid']));
        
        //end 获取医院下(pi)的病人数
        return $ok;
    }
    /*
    * desc: 移动病人至其它医生下
    *       同时更新项目的病人数(num_joined)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function movePatient($projectid, $patientid, $doctorid_new, $doctorid_old)
    {   
        $logfile = 'MProject';
        if(empty($patientid)){
            CLog::WriteLog(array('msg'=>'patientid不合法', 'patientid'=>$patientid), $logfile);
            return false;
        }
        $project = $this->getProject($projectid);
        if(!$project){
            CLog::WriteLog(array('msg'=>'项目不存在', 'projectid'=>$projectid), $logfile);
            return false;
        }

        $MUser = $this->LoadApiModelMedical('user');
        // print_r($patientArr);
        $patient = $MUser->getUser($patientid, array('role'=>-10));
        // var_dump($patient);
        if(empty($patient)){
            CLog::WriteLog(array('msg'=>'病人不存在', 'patientid'=>$patientid), $logfile);
            return false;
        }

        $doctor  = $MUser->getUser($doctorid_new, array('role'=>10));
        if(empty($doctor)){
            CLog::WriteLog(array('msg'=>'doctorid_new不存在', 'doctorid_new'=>$doctorid_new), $logfile);
            return false;
        }
        $doctor_old  = $MUser->getUser($doctorid_old, array('role'=>10));
        if(empty($doctor_old)){
            CLog::WriteLog(array('msg'=>'doctorid_old不存在', 'doctorid_old'=>$doctorid_old), $logfile);
            return false;
        }

        // var_dump($doctorid_old);

        //准备要修改的病人记录(多条)
        $upArr = array(
            'doctorid'    => $doctorid_new,
        );
        CLog::WriteLog(array('msg'=>'执行updateData', 'upArr'=>$upArr), $logfile);
        $ok = $this->updateData($this->tPPatient, $upArr, array('projectid'=>$projectid,/*'doctorid'=>$doctorid_old,*/'patientid'=>$patientid));
        if(!$ok)return false;

        //获取项目下病人的个数(项目下的不用更新)
        /*$num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid));
        if($num_patient > 0){
            $this->updateProject($projectid, array('num_patient'=>$num_patient));
        }*/
        //end 获取项目下病人的个数

        //获取医生下的病人数,包括新、旧
        //新
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'doctorid'=>$doctorid_new));
        $this->updateData($this->tPDoctor, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'doctorid'=>$doctorid_new));
        //旧
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'doctorid'=>$doctorid_old));
        $this->updateData($this->tPDoctor, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'doctorid'=>$doctorid_old));
        //end 获取医生的个数

        //获取医院下(pi)的病人数,包括新、旧
        //新
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid']));
        $this->updateData($this->tPPi, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid']));
        //旧
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'hospitalid'=>$doctor_old['hospitalid']));
        $this->updateData($this->tPPi, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'hospitalid'=>$doctor_old['hospitalid']));
        //end 获取医院下(pi)的病人数
        return true;
    }
    /*
    * desc: 为一项目添病人(医生有权限)
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function appendTpl($projectid, $tplArr, $overwrite=false)
    {
        if(empty($tplArr) || !$projectid)return false;
        $project = $this->getProject($projectid);
        if(!$project)return false;

        $MTpl = $this->LoadApiModelMedical('tpl');
        // print_r($tplArr);
        $tplid_arr = $tplArr['tplid'];
        $tplArr = $MTpl->getTpls($tplid_arr, null, array('limit'=>count($tplid_arr),'only_data'=>true,'aggregated'=>true));
        if(empty($tplArr))return false;

        $addArr = array(); //准备要加的模板记录(多条)
        foreach($tplArr as $tpl){
            $addArr[] = array(
                'tplid'       => $tpl['id'],
                'projectid'   => $projectid,
                'tplname'     => $tpl['name'],
                'ctime'       => date("Y-m-d H:i:s"),
            );
        }
        // print_r($addArr);
        if($overwrite){
            $this->deleteData($this->tPTpl, array('projectid'=>$projectid,'replaced'=>true), 1000);
        }
        return $this->addMore($this->tPTpl, $addArr, array('ignored'=>true));
    }
    /*
    * desc: 为一项目添医生(pi有权限)
    *       同时更新项目的医生数(num_joined)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function removePI($projectid, $adminid, $piid)
    {
        if(empty($piid))return false;
        $project = $this->getProject($projectid);
        if(!$project)return false;

        $MUser = $this->LoadApiModelMedical('user');
        $admin = $MUser->getUser($adminid);
        if(!$admin) return false;

        $whArr = array(
            'projectid' => $projectid,
            'piid'      => $piid,
        );
        // print_r($whArr);
        $ok = $this->deleteData($this->tPPi, $whArr);
        //获取项目pi总数
        $num_pi = $this->getCount($this->tPPi, array('projectid'=>$projectid));
        $this->updateProject($projectid, array('num_pi'=>$num_pi));
        //end 获取项目pi个数

        return $ok;
    }
    /*
    * desc: 为一项目添医生(pi有权限)
    *       同时更新项目的医生数(num_joined)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function removeDoctor($projectid, $piid, $doctorid)
    {
        if(empty($doctorid))return false;
        $project = $this->getProject($projectid);
        if(!$project)return false;

        $MUser = $this->LoadApiModelMedical('user');
        $pi = $MUser->getUser($piid);
        if(!$pi) return false;

        $whArr = array(
            'projectid' => $projectid,
            'piid'      => $piid,
            'doctorid'  => $doctorid,
        );
        $ok = $this->deleteData($this->tPDoctor, $whArr, 500);

        //获取项目下医生的个数
        $num_doctor = $this->getCount($this->tPDoctor, array('projectid'=>$projectid));
        $this->updateProject($projectid, array('num_doctor'=>$num_doctor));
        //end 获取项目下医生的个数
        
        //获取pi下的医生数
        $num_doctor = $this->getCount($this->tPDoctor, array('projectid'=>$projectid,'piid'=>$piid));
        // var_dump($num_doctor);
        $this->updateData($this->tPPi, array('num_doctor'=>$num_doctor), array('projectid'=>$projectid,'piid'=>$piid));
        //end 获取pi下的医生数

        return $ok;
    }
    /*
    * desc: 为一项目添病人(医生有权限)
    *       同时更新项目的病人数(num_joined)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               )
    */
    public function removePatient($projectid, $doctorid, $patientid)
    {
        if(empty($patientid))return false;
        $project = $this->getProject($projectid);
        if(!$project)return false;

        $MUser = $this->LoadApiModelMedical('user');
        $doctor = $MUser->getUser($doctorid);
        if(!$doctor) return false;

        $whArr = array(
            'projectid' => $projectid,
            'doctorid'  => $doctorid,
            'patientid' => $patientid,
        );
        $ok = $this->deleteData($this->tPPatient, $whArr, 500);

        //获取项目下病人的个数
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid));
        $this->updateProject($projectid, array('num_patient'=>$num_patient));
        //end 获取项目下病人的个数
        
        //获取医生下的病人数
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'doctorid'=>$doctorid));
        $this->updateData($this->tPDoctor, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'doctorid'=>$doctorid));
        //end 获取医生的个数

        //获取医院下(pi)的病人数
        $num_patient = $this->getCount($this->tPPatient, array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid']));
        $this->updateData($this->tPPi, array('num_patient'=>$num_patient), array('projectid'=>$projectid,'hospitalid'=>$doctor['hospitalid']));
        //end 获取医院下(pi)的病人数

        return $ok;
    }
    /*
    * desc: 更新一个项目
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               project --- 项目信息
    *               )
    *
    */
    public function updateProject($projectid, $postArr, $piArr=array(), $doctorArr=array(), $patientArr=array(), $tplArr=array())
    {
        $retArr = array('status'=>0, 'message'=>'', 'project'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr) || !$projectid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getProject($projectid);
        if(!$old){
            $retArr['message'] = '项目不存在';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");

        if(!empty($postArr['name'])){
            $oldt = $this->getProject(null,array('hospitalid'=>$old['hospitalid'],'name'=>$postArr['name']));
            if($oldt){
                $this->appendPI($old['id'], $piArr);
                $this->appendDoctor($old['id'], $old['userid'], $doctorArr);
                $this->appendPatient($old['id'], $old['userid'], $patientArr);
                $this->appendTpl($old['id'], $tplArr);

                $retArr['project'] = $old;
                $retArr['message'] = '项目名称不可用';
                return $retArr;
            }
        }
        //数据检查==================================end

        $ok = $this->updateData($this->tProject, $postArr, $projectid);
        if(CUtil::NoFalse($ok)){
            $this->appendPI($old['id'], $piArr);
            $this->appendDoctor($old['id'], $old['userid'], $doctorArr);
            $this->appendPatient($old['id'], $old['userid'], $patientArr);
            $this->appendTpl($old['id'], $tplArr);

            $project = $this->getProject($projectid);
            $retArr['project']  = $project;
            $retArr['status']   = 1;
            $retArr['message']  = '更新成功';
        }else{
            $retArr['message']  = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
    public function getAppends($type, $whArr=array(), $exArr=array())
    {
        if(empty($type)) return false;
        $table = 'project_'.$type;
        if('pi' == $type && (!isset($exArr['no_hospital']))){
            $exArr['join']['hospital'] = "hospitalid:id";
        }
        return $this->getMore($table, $whArr, $exArr);
    }
    public function getCounts($type, $whArr=array(), $exArr=array())
    {
        if(empty($type)) return false;
        $table = 'project_'.$type;
        return $this->getCount($table, $whArr, $exArr);
    }
    /*
    * desc: 根据主键删除附属表的一条记录
    *
    */
    public function dropAppended($id, $type)
    {
        if(empty($id) || empty($type)) return false;
        $table = 'project_'.$type;
        return $this->deleteData($table, $id);
    }
    /*
    * desc: 切底删除项目
    * 步骤: 1, 删除project,project_profile表中的数据
    *       2, 将该用户的role设置成10(普通用户)
    *
    */
    public function dropProject($projectid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'project'=>null);
        //数据检查=====================================
        if(!$projectid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id,storeid');
        $old = $this->getProject($projectid);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        //数据检查==================================end
        $ok = $this->deleteData($this->tProject, $projectid);
        if($ok){
            $this->deleteData($this->tPPi,      array('projectid'=>$projectid), 10000);
            $this->deleteData($this->tPDoctor,  array('projectid'=>$projectid), 10000);
            $this->deleteData($this->tPPatient, array('projectid'=>$projectid), 10000);
            $this->deleteData($this->tPTpl,     array('projectid'=>$projectid), 10000);
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
};

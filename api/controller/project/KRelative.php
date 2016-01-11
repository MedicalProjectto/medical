<?php
/**
 * 与项目相关的病人、医生、PI和用户(包括所有)
 *
 *
 *
 *
*/
class KRelative extends CControllerApi{

    /*
    * desc: 与项目相关人员
    * call: curl "http://api.medical.me/project/relative/doctor?token=293f212613390041d44671ea8326cd3d&projectid=98"
    * call: curl "http://115.29.176.160/project/relative/doctor?token=3c9e159394c67b259dfd796f6e1eb706&projectid=98"
    *
    */
    function actionEntry()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page',  1);
        $limit  = $this->get('limit', 1000);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
            'no_hospital'=>1,
            'only_data' => true,
            'fields'=>'^ctime,hospitalid',
        );

        if(!function_exists('__append_role')){
            function __append_role(&$rowArr, $role, $uidfield){
                if(!$rowArr)return $rowArr;
                foreach($rowArr as &$r0001){
                    $r0001['role'] = $role;
                    $r0001['uid']  = $r0001[$uidfield];
                }
            }
        }

        $type = $this->rest(0);
        $typeArr = array('pi'=>20,'doctor'=>10,'patient'=>-10);
        $flipArr = array_flip($typeArr);

        if(1) {
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $roleU = $user['role'];
            $hospitalid = $user['hospitalid'];
            $hospitalid = $this->get('hospitalid',$hospitalid,true);
            
            //获取所有项目
            $MProject = $this->LoadApiModelMedical('project');
            $projectidP = $projectid = $this->get('projectid');

            if(isset($typeArr[$type])){
                $role = $typeArr[$type];
                $uidfield = $flipArr[$role].'id';

                $wh_app = array();
                if($projectid){//获取指定项目的相关人员
                    // $pid_arr = array($projectid);
                    $project = $MProject->getProject($projectid);
                    if(!$project){
                        $this->error('项目不存在');
                    }

                    $makerid = $project['userid'];
                    if($roleU >= 80 || $makerid==$userid){
                        $wh_app = array('projectid'=>$projectid);
                    }else{
                        $wh_app = array(
                            'projectid'=>$projectid,
                            'hospitalid'=>$hospitalid, //其它用户只能看当前医院的用户
                        );
                        if($roleU <= 10 && $roleU > $role) {
                            $wh_app[$flipArr[$roleU].'id'] = $userid;
                        }
                    }
                }else{//获取hospitalid下所有项目的相关人员
                    if(!isset($typeArr[$type])){
                        $this->error('错误的请求');
                    }
                    $projArr = $MProject->getProjects(null,array('hospitalid'=>$hospitalid,'status'=>0),array('fields'=>'distinct id','keyas'=>'id','only_data'=>true));
                    if(!$projArr){
                        $this->error('没有活动项目存在');
                    }
                    $pid_arr = array_keys($projArr);
                    $wh_app  = array(
                        'hospitalid' => $hospitalid,
                        'projectid in'=>$pid_arr
                    );
                }
                $wh_app['username%']  = $this->get('q');
                CFun::removeArrayNull($wh_app, true);
                // print_r($wh_app);
                
                $appendArr = $MProject->getAppends($type,$wh_app,$exArr);
                $uid_arr   = $this->getArrayColumn($appendArr, $uidfield);
                __append_role($appendArr, $typeArr[$type], $uidfield);

            }else{
                if($roleU >= 80){
                        $wh_app = array('projectid'=>$projectid);
                }else{
                    $wh_app = array(
                        'projectid'=>$projectid,
                        'hospitalid'=>$hospitalid, //其它用户只能看当前医院的用户
                    );
                }
                $wh_app['username%']  = $this->get('q');
                CFun::removeArrayNull($wh_app, true);
                // print_r($wh_app);
                $appendArr1 = $MProject->getAppends('pi',$wh_app,$exArr);
                $appendArr2 = $MProject->getAppends('doctor',$wh_app,$exArr);
                $appendArr3 = $MProject->getAppends('patient',$wh_app,$exArr);
                __append_role($appendArr1, 20, 'piid');
                __append_role($appendArr2, 10, 'doctorid');
                __append_role($appendArr3, -10, 'patientid');
                $appendArr = array_merge($appendArr1, $appendArr2, $appendArr3);

                $uid_arr1 = $this->getArrayColumn($appendArr,'piid');
                $uid_arr2 = $this->getArrayColumn($appendArr,'doctorid');
                $uid_arr3 = $this->getArrayColumn($appendArr,'patientid');
                $uid_arr  = array_merge($uid_arr1, $uid_arr2, $uid_arr3);;
                $uid_arr  = array_unique($uid_arr);
            }
            if(false !== $appendArr){
                if($appendArr){
                    $pid_arr = $this->getArrayColumn($appendArr,'projectid');
                    $projectArr = $MProject->getProjects($pid_arr,null,array('only_data'=>true,'fields'=>'^notice,remark,status,hospitalid,fullname,opendate,closedate'));
                    // print_r($projectArr);
                    $appendArr = $this->joinToArray($appendArr, $projectArr, 'projectid:id', 'project');  
                    $userArr  = $MUser->getUsers($uid_arr, null, array('only_data'=>true,'limit'=>count($uid_arr),'fields'=>'id,idcard,mobile,tel,age,username,height,weight,ethnic,avatar'));
                    $appendArr = $this->joinToArray($appendArr, $userArr, 'uid:id', 'user');
                }
                $this->response($appendArr);
            }
        }
        $this->error('没有找到用户信息');
    }
};

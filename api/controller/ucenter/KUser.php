<?php
/**
 *
 *
 *
 *
 *
*/
class KUser extends CControllerApi{
 
    /*
    * desc: 默认控制器的默认方法
    *
    *
    */
    function actionEntry()
    {
        header("cache-control: public");
        $session = $this->getSession();
        $useridS = $session->get('userid_sess');
        $roleS   = $session->get('role_sess');
        $storeid   = $session->get('storeid_sess');
 
        $page  = $this->get('page',1);
        $limit = 50;
        $MUser = $this->LoadApiModelMedical('user');
        // exit;
        $exArr = array('page'=>$page,'limit'=>$limit, 'fields'=>'*');
        $whArr = array('storeid'=>$storeid);

        //处理过滤条件
        // $this->_set_query_where($whArr);
        $this->assign_query_where($whArr, "role,or:(username%.email%.mobile):username");
        //end 处理过滤条件

        $exArr['order'] = 'id desc';
        $exArr['join_wx'] = true;
        $dataArr = $MUser->getUsers(null, $whArr, $exArr);
        $userArr = $dataArr['data'];
        $total   = $dataArr['total'];
        
        $privilegesArr = $this->getPrivileges();
        $departmentArr = $this->getDepartment();
        $tree = new CTree();
        $departmentArr = $tree->treeToDimensions($departmentArr);
        $dutyArr = $this->getDuty();

        $pager = CPager::APager($page,$total,$limit);
        $this->assign('userArr', $userArr);
        $this->assign('total',   $total);
        $this->assign('pager',   $pager);
        $message = $session->flushMessage();
        $this->assign('message', $message);
        $this->assign('privilegesArr',   $privilegesArr);
        $this->assign('departmentArr',   $departmentArr);
        $this->assign('dutyArr',   $dutyArr);
        $this->assign('current',   'list');

        $this->getize();
        $this->display('user');
    }


    /*
    * desc: 搜索用户(ajax)
    * curl "http://api.medical.me/ucenter/user/search/pi?token=ae75f9cdce0bac75828515149eeecb72"
    *
    */
    public function actionSearch()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 2000);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
            'order' => 'role desc, username asc',
            'join_hospital' => true,
        );
        $rArr = array('pi'=>20,'doctor'=>10,'patient'=>-10);
        if($rest1 = $this->rest(1)){
            $role = isset($rArr[$rest1])?$rArr[$rest1]:null;
        }else{
            $role = $this->get('role');
        }

        if($role || 1) {
            $q = $this->get('q');
            $whArr = array(
                'hospitalid'=>$this->get('hospitalid'),
                'role<'=>90,
                'role'=>$role,
                'enabled>'=>-1,
                'or'=>array(
                    'username%'=>$q,
                    'mobile%'=>$q,
                    ),
            );
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MUser = $this->LoadApiModelMedical('user');
            $dataArr = $MUser->getUsers(null,$whArr,$exArr);
            // print_r($dataArr);
            if(is_array($dataArr)){
                $this->response($dataArr);
            }
        }   
        $this->error('没有找到用户');
    }
    /*
    * desc: 用户加入的...
    * curl "http://api.medical.me/ucenter/user/joined?token=358a2ca1de73dce08734df631df40c1b"
    *
    */
    public function actionJoined()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 20);
        $exArr  = array(
            'page'   => $page,
            'limit'  => $limit,
            'fields' => '^ctime',
            'order'  => 'id desc',
            'join'   => array('project'=>'projectid:id')
        );
        $role = $this->get('role');
        $q = $this->get('q');
        $whArr = array(
            'patientid' => $userid,
        );
        CFun::removeArrayNull($whArr);
        // print_r($whArr);

        $MProject = $this->LoadApiModelMedical('project');
        $dataArr  = $MProject->getAppends('patient',$whArr,$exArr);
        // print_r($dataArr);
        if(is_array($dataArr)){
            $this->response($dataArr);
        }
        $this->error('没有找到用户');
    }
    /*
    * desc: 后台添加用户
    * call: curl -d "username=aa&mobile=15298603022&idcard=123456789123456711&role=10" http://api.medical.me/ucenter/user/add?token=8bf3ce3551bfa311da971e001ca3a391
    *
    */
    function actionAdd()
    {
        $adminid = $this->userid;//管理员的userid

        $postArr = $this->posts('idcard,mobile,email,tel,username,status,sex,truename,hospitalid,deptid,plain,role,height,weight,ethnic,duty,education,birthplace,address,enabled,num_ill,date_in,date_out,deptname');

        if($this->isPost()) {
            $postArr['enabled'] = 1;
            $MUser  = $this->LoadApiModelMedical('user');
            $admin  = $MUser->getUser($adminid);
            $role   = intval($admin['role']);
            $roleto = $this->post('role'); //要添加用户的角色
            if(90 == $role){
                $retArr = $MUser->addUser($postArr);
            }else{
                if($roleto >= $role || $admin['hospitalid'] != $postArr['hospitalid']){
                    $this->error('无权限操作');
                }
                $retArr = $MUser->addUser($postArr);
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $user  = $retArr['user'];
                if(-10 == $roleto && $role < 80){
                    //添加的病人,还要添加关系
                    // $MUser->addPatient($adminid, $user['id']);
                }
                $this->response($user);
            }else{
                $this->error($retArr['message']);
            }
        }
        $this->error('添加用户失败');
    }
 
    //删除用户
    function actionDrop()
    {
        $adminid = $this->userid;//管理员的userid

        if($this->isPost() && $userid=$this->post('userid')){
            $MUser = $this->LoadApiModelMedical('user');
            $admin = $MUser->getUser($adminid);
            if($admin){
                $role = intval($admin['role']);
                if(90 == $role){
                    $ok = $MUser->dropUser($userid);
                    if($ok){
                        $this->response('用户已删除');
                    }
                }elseif(80 == $role){
                    $user = $MUser->getUser($userid);
                    if($user && $user['role'] < 80 && $user['hospitalid']==$admin['hospitalid']){
                        //只能删除比自己权限小的并为同一家医院的用户
                        $ok = $MUser->dropUser($userid);
                        if($ok){
                            $this->response('用户已删除');
                        }
                    }
                }
            }
        }
        $this->error('无权限操作');
    }
    /*
    * desc: 禁用/启用用户
    *
    * call: curl http://api.medical.me/ucenter/user/status/enable?token=59a76a4e2036b33af0102da08118ee47
    * call: curl http://api.medical.me/ucenter/user/status/disable?token=b658efc03bbf5e9d8195c18bf2619ca8 -d "hospitalid=120"
    *
    */
    function actionStatus()
    {
        $userid = $this->userid;//管理员的userid
        $otype  = $this->rest(0);

        if($useridP = $this->post('userid' )){
            $MUser     = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            $her  = $MUser->getUser($useridP);
            if($user && $her && intval($her['role'])<90){
                $role = intval($user['role']);
                if($role >= 80){
                    if('enable' == $otype){
                        $ok = $MUser->updateUser($useridP, array('enabled'=>1));
                    }elseif('disable' == $otype){
                        if(!$MUser->isDisabled($useridP)){
                            $this->error('活动用户，不能移除');
                        }
                        $ok = $MUser->updateUser($useridP, array('enabled'=>-1));
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
    * desc: 重设密码
    * 
    * call: curl -d "pass_old=123456&pass_new1=123&pass_new2=123" http://api.medical.me/ucenter/user/passwd?token=7a8833fc683d66021f4176740548a466
    */
    function actionPasswd()
    {
        $userid = $this->userid;

        if($this->isPost()){
            $pass_old  = $this->post('pass_old');
            $pass_new1 = $this->post('pass_new1');
            $pass_new2 = $this->post('pass_new2');
            if($pass_old && $pass_new1){
                $MUser = $this->LoadApiModelMedical('user');
                $ok = $MUser->repassword($userid, $pass_new1, $pass_new2, $pass_old);
                if($ok){
                    $MUser->offline($userid);
                    $this->response(array('password'=>$ok));
                }
            }
        }
        $this->error('修改密码失败');
    }

    /*
    * desc: 更新用户资料
    * call: curl http://api.medical.me/ucenter/user/profile?token=a6078a26a9bc920d016ba9a94fde319a -d "userid=160&username=pp1"
    *
    */
    function actionProfile()
    {
        //用户信息(可修改)
        // $userid = $this->userid;
        $userid = $this->getf('userid',$this->postf('userid',$this->userid));

        $MUser = $this->LoadApiModelMedical('user');
        if($this->isPost()){
            //更新profile
            $postArr = $this->posts('email,username,sex,ethnic,age,tel,height,weight,truename,deptid,duty,education,birthplace,address,avatar,num_ill,date_in,date_out,deptname,doctorid');
            $retArr = $MUser->updateUser($userid, $postArr);
            if(!$retArr['status']){
                $this->error($retArr['message'], 200);
            }
        }
        $info = $MUser->getUser($userid, null, array('join_hospital'=>true));
        // CLog::WriteLog($info,'z2.log');
        if($info){
            /*if(-10 == intval($info['role'])){
                $info = $MUser->getUser($userid, null, array('join_profile'=>true));
            }*/
            $this->response($info);
        }
        $this->error('获取资料失败', 500);
        // print_r($info);
    }

    /*
    * 导入用户
    *
    *
    *call: curl http://api.medical.me/ucenter/user/import?token=3d8d0bf9bee3340fb2930f430b0bdbb3 -d "filexls=D:\sites\medical\_data\static\tpl-patient-import.xls"
    */
    function actionImport()
    {
        $userid = $this->userid;
        $MUser = $this->LoadApiModelMedical('user');
        $user  = $MUser->getUser($userid);

        if($user && $this->post('filexls')){
            $time_before = CTool::getUTime();

            $filexls = $this->post('filexls');
            
            $xls = new CExcel();
            $uploadLoc = $this->getLoc('_data');
            $filexls  = $uploadLoc . $filexls;
            $sheetArr = $xls->getData($filexls);
            // print_r($sheetArr);exit;
            // exit;
            /*
            excel中的数据分为三段:
                1, 基本信息
                2, 规格信息
                3, 详细信息
                其中2,3统统存放到detail中
            */
            if($sheetArr){
                foreach($sheetArr as $sheet => $dataArr){
                    // print_r($dataArr);exit;
                    if($dataArr){
                        //----------------------------------------------------
                        $fields = $dataArr[1];  //第1行为字段信息
                        // print_r($fields);exit;
                        $pos = array_search('-', array_values($fields)); //第一个'-'的位置
                        // print_r($fields);
                        $bases = $fields; //基本信息(arr)
                        $specs = array_slice($fields, $pos+1);  //规格信息(arr)
                        // $this->dump($fields);
                        //----------------------------------------------------

                        //----------------------------------------------------
                        $xvalArr  = array_slice($dataArr, 1);
                        $valueArr = $partionArr = array();
                        $restype  = $this->post('restype');
                        // print_r($dataArr);exit;
                        foreach($xvalArr as $k=>$xrow){
                            if(empty($xrow['A']) && !isset($last_row))break; //数据有问题
                            $row = array();
                            
                            // if(empty($xrow['A']))continue; //第一栏为空
                            $attnameArr = $attvalArr = array(); //属性名和属性值
                            foreach($bases as $c=>$field){
                                //c为excel中的列名(A,B...)
                                //field为excel中的第一行的值(业务上的字段) 产品品牌
                                $field = trim($field);
                                $xval  = trim($xrow[$c]);
                                $row['hospitalid'] = $user['hospitalid'];

                                if('身份证号'==$field){
                                    $row['idcard'] = trim($xval);
                                }elseif(false !== strpos($field, '手机号')){
                                    $row['mobile'] = floatval($xval);
                                }elseif(false !== strpos($field, '姓名')){
                                    $row['username'] = trim($xval);
                                }elseif(false !== strpos($field, '年龄')){//针对机械
                                    $row['age'] = trim($xval);
                                }elseif(false !== strpos($field, '角色id')){
                                    $row['role'] = intval($xval);
                                }elseif(false !== strpos($field, '所属医院')){
                                    /*$title  = trim($xval);
                                    $MHospital = $this->LoadApiModelMedical('hospital');
                                    $hospital = $MHospital->getHospital(null,array('title'=>$title));
                                    if($hospital){
                                        $row['hospitalid'] = $hospital['id'];
                                    }*/
                                }elseif(false !== strpos($field, '科室')){
                                    $row['deptname'] = trim($xval);
                                }elseif(false !== strpos($field, '民族ID')){
                                    $row['ethnic'] = trim($xval);
                                }elseif(false !== strpos($field, '地址')){
                                    $row['address'] = trim($xval);
                                }elseif(false !== strpos($field, '电话')){
                                    $row['home_phone'] = trim($xval);
                                }elseif(false !== strpos($field, '住院号')){
                                    $row['num_ill'] = trim($xval);
                                }elseif(false !== strpos($field, '入院日期')){
                                    $row['date_in'] = trim($xval);
                                }elseif(false !== strpos($field, '出院日期')){
                                    $row['date_out'] = trim($xval);
                                }
                            }
                            $valueArr[] = $row;
                        }
                        // print_r($valueArr);exit;
                        //----------------------------------------------------
                        // break; detail
                    }
                    break;  //只读一页
                }
            }

            //导入数据========================================
            // print_r($valueArr);
            $dataArr = array();
            $importinfos = array();
            if($valueArr){
                $MUser = $this->LoadApiModelMedical('user');
                foreach($valueArr as $row){
                    if(empty($row['hospitalid']) || empty($row['idcard']) || empty($row['username'])){
                        $importinfos[] = array('code'=>1, 'message'=>'数据不合法');
                        continue;
                    }
                    $retArr = $MUser->addUser($row);
                    $importinfos[] = array('code'=>(1==$retArr['status']?0:1), 'message'=>$retArr['message']);
                }
                // print_r($retArr);
                // print_r($valueArr);
                // @unlink($filexls);
            }
            $time_after = CTool::getUTime();
            $Elapse = sprintf("%.4f", $time_after - $time_before);
            $dataArr['elapsed'] = $Elapse;
            $dataArr['total']   = count($valueArr);
            $dataArr['importinfos'] = $importinfos;
            //end 导入数据====================================

            // $jArr['data'] = $valueArr;
            $this->response($dataArr);
        }
    }
};

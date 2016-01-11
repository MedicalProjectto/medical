<?php
/**
 * 用户授权相关
 *
 *
 *
 *
*/
class KAuth extends CControllerApi {
    
    function __construct(){}
    /*
    * desc: 登录
    *
    *
    * call: curl -XPOST http://api.medical.me/auth/login -d "loginname=13123666111&password=c33367701511b4f6020ec61ded352059"
    * call: curl -XPOST http://115.29.176.160/auth/login -d "loginname=13123777777&password=f63f4fbc9f8c85d409f2f59f2b9e12d5"
    */
    function actionLogin()
    {
        $jArr = array('status'=>0, 'message'=>'非法请求');

        $time_before = CTool::getUTime();

        $postArr   = $this->posts('loginname,password');
        $loginname = $postArr['loginname'];
        if($this->isPost()){
            $MUser = $this->LoadApiModelMedical('user');
            $exArr = array('join_pass'=>true);
            if(CUtil::IsIdcard($loginname)){
                $whArr = array('idcard' => $loginname);
            }else{
                $whArr = array('mobile' => $loginname);
            }
            $user = $MUser->getUser(null, $whArr, $exArr);
            // print_r($user);

            if($user && isset($user['user_pass'])){
                $pass = &$user['user_pass'];
                if($postArr['password'] == $pass['password']){
                    // print_r(json_decode($user['privileges'],true));
                    $role   = $user['role'] = intval($user['role']);
                    $userid = $user['id'];
                    //生成token------------------
                    $lastime = date("Y-m-d H:i:s");
                    $retArr  = $MUser->updateUser($userid, array('lastime'=>$lastime));
                    $token   = $MUser->makeToken($userid, $lastime);
                    // $MUser->verifyToken($token);
                    //生成token---------------end
                    $jArr['status'] = 1;
                    $jArr['token']  = $token;
                    $this->response(array('token'=>$token,'role'=>$role,'userid'=>$userid));
                }else{
                    $jArr['message'] = '密码不正确';
                }
                unset($pass['password']);
            }else{
                $jArr['message'] = '用户不存在';
            }
        }

        $time_after = CTool::getUTime();
        $elapsed = sprintf("%.4f", $time_after - $time_before);
        $jArr['elapsed'] = $elapsed;

        // print_r($jArr);
        // $this->output($jArr);
        $this->error($jArr['message']);
    }

    function actionLogout()
    {
        $jArr = array('status'=>0, 'message'=>'操作出现异常');
        $MUser = $this->LoadApiModelMedical('user');
        $retArr  = $MUser->updateUser($userid, array('lastime'=>date("Y-m-d H:i:s")));
        if(1 == intval($retArr['status'])){
            $this->message('已正常退出');
        }
        $this->error('登录失败');
    }
};

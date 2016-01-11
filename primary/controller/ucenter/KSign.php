<?php
/**
 * desc: 包括登录和注册
 *
 *
*/
class KSign extends CControllerPrimary {

    function actionEntry()
    {
    }
    private function _save_data_session($datas)
    {
        $session = $this->getSession();
        if (!is_array($datas)){
            $session->set($datas, $datas);
            return;
        }
        foreach ($datas as $f => $v) {
            $fs = $f . '_sess';
            $session->set($fs, $v);
        }
    }
    /*
    * desc: 网站正常登录(提供用户名和密码)
    *
    *
    */
    function actionLogin()
    {
    }
    private function _parse_state($state)
    {
        $appxArr = array();
        if(empty($state))return $appxArr;
        $arr = explode(',', $state);
        foreach($arr as $item){
            if(!strpos($item, '-'))continue;
            list($k,$v) = explode('-', $item);
            $appxArr[$k] = $v;
        }
        $appxArr['state'] = $state;
        return $appxArr;
    }
    private function _get_wx_auth_url($paramters=array())
    {
        //scope登录方式(snsapi_base:基础方式,snsapi_userinfo:授权登录)
        $paramters['scope'] = (isset($paramters['scope'])&&$paramters['scope'])?'snsapi_userinfo':'snsapi_base';
        // CLog::WriteLog($paramters, 'paramters');
        $authurl = CPlatform::MakeWpAuthURL($mpid, $paramters);
        return $authurl;
    }
    /*
    * desc: 微信登录(wx base)
    *
    */
    function actionWxlogin()
    {
        $session = $this->getSession();
        //获取传播参数
        if (!$this->isLogined()) {
            $code  = $this->get('code');
            $state = $this->get('state');
            $appxArr = $this->_parse_state($state);
            $mpid = empty($appxArr['mpid'])?(CPlatform::$MPID10):$appxArr['mpid'];
            if(!$code){
                //触发登录weixin
                $authurl = $this->_get_wx_auth_url($appxArr);
                $this->location($authurl);
            }

            //获取微信唯一标识unionID
            $MWechat = $this->LoadApiModel('wechat');
            $wxinfoArr = $MWechat->getUserInfo($mpid, $code);
            // $this->dump($wxinfoArr);exit();
            if(isset($wxinfoArr['errcode']) && $wxinfoArr['errcode']){
                // $this->location($authurl);
                $authurl = $this->_get_wx_auth_url($appxArr);
                $this->location($authurl);
            }
            $unionid = isset($wxinfoArr['unionid']) ? $wxinfoArr['unionid'] : null;
            //根据unionID获取userID
            $MUser = $this->LoadApiModel('user');
            $userInfo = $MUser->getUser(null, array (
                'unionid' => $unionid
            )); //不存在则创建user
            // $this->dump($wxinfoArr);
            $addArr = $wxinfoArr;
            // $this->dump($addArr);exit;
            $addArr['username'] = $addArr['truename'] = isset ($wxinfoArr['nickname']) ? $wxinfoArr['nickname'] : null;
            $addArr['avatar'] = isset ($wxinfoArr['headimgurl']) ? $wxinfoArr['headimgurl'] : null;
            $addArr['type'] = 10;
            $addArr['mpid'] = $mpid; //公众号的标识id
            $addArr['projectid'] = CProject::$PRO_PYS_ID; //项目的标识id(一个项目可包含多个公众号)
            
            if(!$userInfo) {
                $row = $MUser->addUser($addArr);
                $userInfo = $row['user'];
            }
            $MUser->addMpUser($mpid, $wxinfoArr['openid'], $addArr);
            
            $sessArr = array(
                'userid'   => $userInfo['id'],
                'time'     => time(),
                'role'     => $userInfo['role'],
                'username' => $userInfo['username'],
                'avatar'   => $userInfo['user_profile']['avatar'],
            );
            
            //检查store=================================
            if(50 == intval($userInfo['role'])){
                
                $MStore = $this->LoadApiModel('store');
                $store = $MStore->getStore(null, array('userid'=>$userInfo['id']));
                if($store){
                    //默认为商家分店
                    $sessArr['usertype'] = 50;
                    $sessArr['storeid'] = $store['id'];
                    $whr['userid']=$userInfo['id'];
                    $arr = $MStore->getGroup(null,$whr);
                   if($arr){
                       $sessArr['usertype'] = 52;
                       $sessArr['groupid'] = $arr['id'];
                   }
                }
            }
            //检查store==============================end
            $this->_save_data_session($sessArr);

        }
        $redirectUrl = $session->flushUrl();
        if ($redirectUrl)
            $this->location($redirectUrl);
    }
    
    /*
    * desc: 提供给商户的简单登录方式
    *
    *
    *
    */
    function actionSamplelogin() {
        $session = $this->getSession();
        //获取传播参数
        if ($this->isPost()) {
            $username = $this->post('username');
            $MUser = $this->LoadApiModel('user');
            $userInfo = $MUser->getUser(null, array (
                'username' => $username,
                'role' => 50
            )); //不存在则创建user
            if ($userInfo) {
                $MStore = $this->LoadApiModel('store');
                $store = $MStore->getStore(null, array (
                    'userid' => $userInfo['id']
                ));
                if ($store) {
                    $sessArr = $userInfo;
                    $sessArr['userid'] = $userInfo['id'];
                    $sessArr['storeid'] = $store['id'];
                    $sessArr['time'] = time();
                    // $this->dump($sessArr);
                    $this->_save_data_session($sessArr);
                    $redirectUrl = $session->flushUrl();
                    if ($redirectUrl)
                        $this->location($redirectUrl);
                } else {
                    $this->assign('message', '非商户禁止登录');
                }
            } else {
                $this->assign('message', '非法用户');
            }
            $this->assign('username', $username);
        }
        $this->display('samplelogin');
    }

    function actionLogout() {
        $session = $this->getSession();
        $session->clean();
        // $this->setGlobalVariables();
        // $this->assign('message', '已安全退出!');
        $session->pushMessage('已安全退出');
        header('Location: /ucenter/sign/login');
        $this->display('login');
    }

    function actionRegister() {
        $session = $this->getSession();
        $jArr = array (
            'status' => 0,
            'message' => ''
        );
        if ($this->isPost()) {
            $jArr = array (
                'status' => 0,
                'data' => null,
                'time' => time()
            );
            $captchaP = $this->post('captcha');
            $captchaS = $session->get('captcha');
            if ($captchaP != $captchaS) {
                $jArr['status'] = 0;
                $jArr['message'] = '验证码错误';
                exit (json_encode($jArr));
            }
            $postArr = $this->posts('loginname,plain1,plain2,autopass,refurl');
            extract($postArr);
            $refurlP = $postArr['refurl'];
            if ($plain1 != $plain2) {
                $jArr['message'] = "两次输入的密码不一致";
            } else {
                if (!empty ($plain1))
                    $postArr['plain'] = $plain1;
                if ($postArr['autopass'] == 'on') {
                    //如果选中了自动密码密码
                    unset ($postArr['plain']);
                }
                $postArr['validated'] = 0;
                $postArr['username'] = $postArr['loginname']; //'Ad' . date('Ymd');

                $model = $this->LoadApiModel('user');
                $retArr = $model->addUser($postArr);
                $userInfo = $retArr['user'];
                if ($userInfo) {
                    $plain = $userInfo['plain'];
                    unset ($userInfo['password'], $userInfo['plain']);
                    $jArr['data'] = $userInfo;
                    $jArr['refurl'] = $refurlP;
                    $jArr['status'] = 1;
                    // $jArr['message'] = '恭喜注册帐号"'.$userInfo['email'].'"成功，你的初始密码为：'.$plain;
                    $jArr['message'] = '恭喜注册帐号"' . $userInfo['email'] . '"成功，请牢记你的密码!';
                    //$session->pushMessage('恭喜注册帐号"'.$userInfo['email'].'"成功，你的初始密码为：'.$plain);
                    $session->set('userid_sess', $userInfo['id']);
                    $session->set('username_sess', $userInfo['username']);
                    $session->set('userrole_sess', $userInfo['role']);

                    //发送密码至手机或email
                    if (!empty ($plain)) {
                        if (CCommon :: isMobile($loginname, $mobile)) {
                            $msg = "你的惠而全密码为:{$plain}";
                            $retArr = CSms :: send($mobile, $msg);
                        } else
                            if (CCommon :: isEmail($loginname, $email)) {
                                $url_login = $this->makeUrl('ucenter/sign/login');
                                $msg = "你的惠而全密码为:{$plain},单击此处<a href='{$url_login}'>登录</a>";
                                $mail = $this->LoadApiModel('mail');
                                $mail->send($email, $msg, "惠而全帐号密码");
                            }
                    }
                    //end 发送密码至手机或email

                    //添加购物车到数据库
                    $MCart = $this->LoadApiModel('cart');
                    $MCart->parseFrontCart($cartArr);
                    if (is_array($cartArr) && count($cartArr)) {
                        foreach ($cartArr as & $r2) {
                            $r2['userid'] = $userInfo['id'];
                        }
                        // print_r($cartArr);
                        $id = $MCart->addCarts($cartArr);
                        $MCart->cleanFrontCart(); //清空cookie购物车
                    }
                    //end 添加购物车到数据库

                } else {
                    $jArr['message'] = $retArr['message'];
                }
            }
            exit (json_encode($jArr));
        }

        $refurl = $this->get('refurl');
        if (!$refurl) {
            $refurl = $this->getRef();
        }
        $refignoreArr = array (
            'register',
            'retrieve',
            'login'
        );
        foreach ($refignoreArr as $word) {
            if (strpos($refurl, $word)) {
                $refurl = '/';
                break;
            }
        }
        $this->assign('refurl', $refurl);
        $this->assign('action', 'register');

        $this->display('sign');
    }

    function actionCheck() {
        $jArr = array (
            'status' => 0,
            'message' => ''
        );
        if ($this->isPost()) {
            $postArr = $this->posts('key,val');
            extract($postArr);
            $model = $this->LoadApiModel('user');
            if (CCommon :: isMobile($val, $mobile)) {
                //手机
                if (!empty ($val)) {
                    if ($model->checkExist($mobile, 'mobile')) {
                        $jArr['message'] = '该手机号已存在!';
                    } else {
                        $jArr['status'] = 1;
                        $jArr['message'] = '该手机号可以使用!';
                    }
                } else {
                    $jArr['message'] = '手机号不能为空!';
                }
            } else {
                //email
                if (!empty ($val)) {
                    if (CCommon :: isEmail($val)) {
                        if ($model->checkExist($val, 'email')) {
                            $jArr['message'] = '该EMail已存在!';
                        } else {
                            $jArr['status'] = 1;
                            $jArr['message'] = '该E-Mail可以使用!';
                        }
                    } else {
                        $jArr['message'] = '格式不正确';
                    }
                } else {
                    $jArr['message'] = 'E-Mail不能为空!';
                }
            }
            exit (json_encode($jArr));
        } else { //GET
            $this->httpError(403);
        }
    }

    /*
    * desc: 密码找回
    *
    *
    */
    function actionRetrieve() {
        $session = $this->getSession();
        $captchaP = $this->post('captcha');
        $captchaS = $session->get('captcha');
        $tkey = '894twoeijhf9fasoiudf';

        if ($this->isPost() && $captchaP == $captchaS) {

            $jArr = array (
                'status' => 0,
                'message' => ''
            );

            if ($session->get($tkey) && time() - intval($session->get($tkey)) < 60) {
                $jArr['message'] = '你提交得过快!';
                exit (json_encode($jArr));
            }

            $MUser = $this->LoadApiModel('user');
            $loginname = $this->post('loginname');

            if (CCommon :: isMobile($loginname, $mobile)) {
                //手机号
                //1,获取记录
                $row = $MUser->getUser(null, array (
                    'mobile' => $mobile
                ));
                //2,将plain发送手机
                if ($row) {
                    $msg = "您的惠而全帐号密码为:" . $row['plain'];
                    $retArr = CSms :: send($mobile, $msg);
                    //end 将code发送手机
                    if ($retArr['sms_status']) {
                        $jArr['code'] = $retArr['sms_code'];
                        $jArr['message'] = '密码已发送到您的手机!';
                        $jArr['status'] = 1;
                    } else {
                        $jArr['message'] = $retArr['sms_message'];
                    }
                } else {
                    $jArr['message'] = '该手机号码不存在';
                }
            }
            elseif (CCommon :: isEmail($loginname, $email)) {
                //email
                //1,获取记录
                $row = $MUser->getUser(null, array (
                    'email' => $email
                ));
                //end 生成code

                if ($row) {
                    //2,发送邮件
                    $plain = $row['plain'];
                    $msg = "您的惠而全帐号密码为:{$plain}";
                    $mail = $this->LoadApiModel('mail');
                    $ok = $mail->send($email, $msg, "惠而全密码找回");
                    //end 发送邮件
                    if ($ok) {
                        //邮件发送成功
                        $jArr['message'] = "邮件已发送，请注意查收";
                        $jArr['code'] = $row['code'];
                        $jArr['status'] = 1;
                    }
                }
            } else {
                //数据有误
                $jArr['message'] = '请正确输入你的EMail或手机号!';
            }
            $session->set($tkey, time());
            // exit(json_encode($jArr));
            $this->output($jArr);
        }
        $this->display('retrieve');
    }
};
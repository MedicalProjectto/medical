<?php
/**
 *
 *
 *
 *
 *
*/
class KToken extends CControllerApi{

    function __construct(){}
    /*
    * desc: 根据一个userid和密码获取token
    *
    * call: http://api.medical.me/ucenter/token?userid=10935&plain=627085
    */
    function actionEntry()
    {
        if($userid = $this->get('userid')){
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid,null,array('join_pass'=>true));
            // $this->dump($user);
            if($user && isset($user['user_pass']['plain']) && $this->get('plain') == $user['user_pass']['plain']){
                echo $MUser->makeToken($userid, $user['lastime']);
            }
        }
    }
    /*
    * desc: 查看token的详细信息
    *
    * call: http://api.medical.me/ucenter/token/information?token=386b710e50c91f9b056a1218a30078fe
    */
    function actionInformation()
    {
        $this->dump($this->LoadApiModelMedical('user')->deToken($this->get('token')));
    }
};

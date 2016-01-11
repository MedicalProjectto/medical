<?php
class CControllerApi extends CController {
    
    protected $userid = 0;

    function __construct(){
        $this->permissionToken();
    }
    /*
    * desc: 验证token
    *
    *
    */
    protected function permissionToken()
    {
        $token = $this->get('token',$this->post('token'),true);
        if(!$token){
            $this->error('无效的token', 403);
        }
        $MUser = $this->LoadApiModelMedical('user');
        $this->userid = $MUser->verifyToken($token);
        if(!$this->userid){
            $this->error('请重新登录', 403);
        }
    }
    protected function response($data=null, $code=0, $message=null)
    {
        $jArr = array('code'=>$code, 'message'=>$message, 'data'=>$data);
        $this->output($jArr);
    }
    protected function message($message=null, $code=0, $data=null)
    {
        $jArr = array('code'=>$code, 'message'=>$message, 'data'=>$data);
        $this->output($jArr);
    }
    
    protected function error($message='服务器错误', $code=67)
    {
        $jArr = array('code'=>$code, 'message'=>$message);
        $this->output($jArr);
    }
    protected function output($var, $format='json', $isexit=true, $iscleanbuff=true)
    {
        // ob_clean();
        if('json' == $format){
            exit(json_encode($var, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        }else{
            print_r($var);
        }
        exit(0);
    }
};

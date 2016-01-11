<?php
/**
 * desc: 邮件管理
 *
 *
 */
class MMail extends CHookModel {
    
    function send($to, $content, $subject="青木云", $name="青木云")
    {
        $mail  = $this->getMail();
        $home  = $this->getConfig('home');
        $from  = $this->getUserConfig('mail');
        $pswd  = $this->getUserConfig('mailpass');
        if(empty($from) || empty($pswd)) return false;
        $mail  = $mail->config('smtp.mxhichina.com', array('from'=>$from,'pswd'=>$pswd,'name'=>$name));
        try{
            return $mail->subject($subject)->body($content)->to($to)->send();
        }catch(Exception $e) {
            return false;
        }
    }
};

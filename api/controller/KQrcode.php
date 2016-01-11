<?php

class KQrcode extends CControllerApi {
    /**
     * 生成二维码
     */
    function actionEntry()
    {
    	$qrcode = new CQrcode();
    	$value = $this->get('value','方寸科技');
    	$max = $this->get('size',10);
    	$level = $this->get('level','L');
    	$filename = $this->get('filename','');
    	$logo = $this->get('logo',false);
    	$qrcode->qrcode($value,$max,$level,$filename,$logo);
    }

    function actionJump()
    {
        $qrid = $this->get('id');
        if($qrid){
            $MQrcode = $this->LoadApiModel('qrcode');
            $qrcode  = $MQrcode->getQrcode($qrid);
            if($qrcode){
                $redirectUrl = $qrcode['url'];
            }
        }else{
            $redirectUrl = $this->get('url');
            if(false === strpos(strtolower($redirectUrl), 'http')){
                $HOME_WWW = $this->getConfig('HOME_WWW');
                $redirectUrl = $HOME_WWW . $redirectUrl;
            }
        }
        if(isset($redirectUrl) && $redirectUrl){
            $this->location($redirectUrl);
        }
    }
};

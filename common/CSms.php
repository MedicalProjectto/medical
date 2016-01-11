<?php
/**
* author: cty@20120701
*   desc: 短信发送模块 
*   url http://sms.webchinese.cn/web_api/?Uid=heqmro&Key=f1979f52d60b18b448d4&smsMob=13913510228&smsText=test
*   f1979f52d60b18b448d4
*   heqmro
*   call: CSms::send(mobile='13912345678,13912345679', content='短信测试')
*         @content --- string(utf8|unicode)
*/

class CSms {
    # __table__ = 'sms'
    # url   =  http://sms.webchinese.cn/web_api/?Uid=heqmro&Key=f1979f52d60b18b448d4&smsMob=13913510228&smsText=test
    static $url  = 'http://222.73.117.158/msg/HttpBatchSendSM?';
    static $account  = 'wxmksr';
    static $pswd  = 'Wxmksr123';
    
    static $statusArr = array(
        0   => '提交成功',
        101  => '没有该用户账户',
        102  => '密码错误',
        103  => '提交过快（提交速度超过流速限制）',
        104 => '系统忙（因平台侧原因，暂时无法处理提交的短信）',
        105 => '敏感短信（短信内容包含敏感词）',
        106  => '消息长度错（>536或<=0）',
        107 => '包含错误的手机号码',
        108 => '',
        109 => '无发送额度（该用户可用短信数已使用完）',
        110 => '不在发送时间内',
        111 => '超出该账户当月发送额度限制',
        112 => '无此产品，用户没有订购该产品',
        113 => 'extno格式错（非数字或者长度不对）',
        115 => '自动审核驳回',
        116 => '签名不合法，未带签名（用户必须带签名的前提下）',
        117 => 'IP地址认证错,请求调用的IP地址不是系统登记的IP地址',
        118 => '用户没有相应的发送权限',
        119 => '用户已过期',
    );

    static function send($mobile, $content=null)
    {
        // url http://sms.webchinese.cn/web_api/?Uid=heqmro&Key=f1979f52d60b18b448d4&smsMob=13913510228&smsText=test
        $req =array('sms_status' => false, 'sms_code'=>0, 'sms_message'=>'');
        // echo "$mobile ==============================\n";
        if($mobile && $content){
            $mobile = preg_replace('/[^0-9\,]/', '', $mobile);
            if(strlen($mobile)>0 && strlen($content)>0){
                $post_data = array();
                $post_data['account'] = iconv('GB2312', 'GB2312',self::$account);
                $post_data['pswd'] = iconv('GB2312', 'GB2312',self::$pswd);
                $post_data['mobile'] = $mobile;
                $post_data['msg']=mb_convert_encoding($content,'UTF-8', 'auto');
                $url = self::$url;

                $code = self::curlPost($url, $post_data);
                $code = explode(',', $code);
                $req['sms_status']  = $code[1] == 0?true:false;
                $req['sms_code']    = $code[1];
                $req['sms_message'] = isset(self::$statusArr[$code[1]]) ? self::$statusArr[$code[1]] : "";
                return $req;
            }
        }
        return $req;
    }

    static function curlPost($url, $postArr=array())
    {
        $o="";
        foreach ($postArr as $k=>$v)
        {
           $o.= "$k=".urlencode($v)."&";
        }
        $post_data=substr($o,0,-1);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
        return curl_exec($ch);
    }
    
    static function curlGet($url, $paraArr=array()) 
    {
        $get  = isset($paraArr['get'])?$paraArr['get']:null;
        $para = is_array($get)&&count($get)>0 ? http_build_query($get): '';
        if(strlen($para) > 0) {
        $url .= (strpos($url, '?') === FALSE ? '?' : '&'). $para;
        }

        $timeOut = isset($paraArr['timeout'])?$paraArr['timeout']:5;
        $ishead  = isset($paraArr['ishead'])?$paraArr['ishead']:false;
        $defaults = array( 
            CURLOPT_URL => $url, 
            CURLOPT_HEADER => $ishead, //是否将头信息作为数据流输出(HEADER信息)
            CURLOPT_RETURNTRANSFER => TRUE, 
            CURLOPT_TIMEOUT => $timeOut
        );
        $headers = array(
            // 'Mozilla/5.0 (Windows NT 6.1; rv:10.0) Gecko/20100101 Firefox/10.0', 
            // 'Accept-Language: zh-cn,zh;q=0.5',
            // 'Accept: */*',
            // 'Connection: keep-alive',
            // 'Accept-Charset: GB2312,utf-8;q=0.7,*;q=0.7', 
            // 'Cache-Control: max-age=0', 
        );
        $ch = curl_init();
        curl_setopt_array($ch, $defaults);  
        if(isset($paraArr['headers']) && is_array($paraArr['headers']) && count($paraArr['headers'])>0) {
            $headers = $headers + $paraArr['headers'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $loops = isset($paraArr['loops'])?$paraArr['loops']:5;
        for($i=1; $i<=$loops; $i++) {
            $result = curl_exec($ch);
            if(false === $result) { usleep(500000 * $i); continue; }
            break;
        }
        
        if(isset($result) && !$result) {
            trigger_error(curl_error($ch));
        }
        if(isset($paraArr['repArr'])) {
            $paraArr['repArr'] = curl_getinfo($ch);
        }
        curl_close($ch);
        return $result; 
    }
};

// CSms::send('15950001413', 'test');
<?php
class CController extends CCtrl {

    protected $isClean = false;
    function __construct(){

    }

    protected function isLogined($expired=7200)
    {
        $session = $this->getSession();
        return $session->get('userid_sess')?true:false;
    }
    protected function saveDataSession($datas)
    {
        $session = $this->getSession();
        if (!is_array($datas)){
            $session->set($datas, $datas);
            return;
        }
        foreach($datas as $f => $v) {
            $fs = $f . '_sess';
            $session->set($fs, $v);
        }
    }

    protected function method($def='POST')
    {
        $def = strtoupper($def);
        return isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:$def;
    }
    protected function ip()
    {
        return isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'';
    }
    protected function isPost()
    {
        return 'POST'==$this->method()?true:false;
    }
    protected function getRef()
    {
        return isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:null;
    }
    protected function location($url)
    {
        exit($this->jump($url));
    }
    protected function jump($url)
    {
        $this->cleanBuffer();
        header("Location: {$url}");
    }
    protected function getize($url=null)
    {
        if($this->isPost()){
            $url = $url?$url:('/'.$this->getRequestUri(''));
            $this->location($url);
        }
    }
    protected function output($var, $format='json', $isexit=true, $iscleanbuff=true)
    {
        if($iscleanbuff) $this->cleanBuffer();
        if('json' == $format){
            echo json_encode($var);
        }else{
            print_r($var);
        }
        if($isexit)exit(0);
    }
    protected function inf($info, $foraced=false, $userid=67)
    {
        if($foraced || $userid==intval($this->getSession()->get('userid_sess'))){
            $this->dump($info);
        }
    }
    protected function runCli($route='cli', $paramters=array())
    {
        $indexcli  = $this->getLoc() . '/building/indexcli.php';
        $paramters = base64_encode(json_encode($paramters));
        $phpbin = CFun::isWindows()?'php':'/usr/local/php/bin/php'; 
        $cmd = $phpbin .' '. $indexcli .' '. $route .' '. $paramters;
        CFun::Process($cmd);
    }

    /*
    * desc: 接收post参数并存入session并赋予smarty
    *
    *@fields1 --- str 字段名(用逗号分隔),可跟sql的操作符,如"title %,id,..."
    *                 如果有同一字段名作多个条件的请用field#1,field#2
    *       "(f1,f2) or (f3,f4)"
    *       "and:(username.*email.*mobie):username %,"
    *@fields2 --- str 和fields1相似，但不作为数据库查询条件
    *
    * whArr = array(
    *           f1 -> 1,
                'or|and' -> array()
    *       )
    */
    protected function assign_query_where(&$whArr, $fields1=null, $fields2=null, $tosmarty=true)
    {
        if(empty($fields1))return;
        $routeid  = crc32($this->getRoute()); //路由id作为session的第一维的健(防止各页面冲突)
        $agArr    = array(); //所有参数(返回值)
        $whArr    = is_array($whArr)?$whArr:array();
        $_f_arr1  = explode(',', trim($fields1));
        $_f_arr2  = explode(',', trim($fields2));
        $fieldArr = array();
        // $this->dump($whArr);
        foreach(array_merge($_f_arr1, $_f_arr2) as $field_str){
            if(!$field_str)continue;
            $field = trim(trim(trim($field_str),'%*+-=><!'));
            $field = preg_replace("/(?:[\%<>\=\*\+\!]+\d*?$|\s[^\s]+?\d*?$)/", '', $field);
            $fieldArr[$field] = $field_str;
        }
        $session = $this->getSession();
        $isPost  = $this->isPost();
        foreach($fieldArr as $field => $field_str){
            if(strpos($field, ':')){ //这是分组模式 post
                //"and:(username.*email.*mobie):username %,role"
                $_field_sqls = substr($field,0, strrpos($field,':'));
                $field = substr($field,strrpos($field,':')+1);
                if(strpos($_field_sqls, ':')){
                    $_orand = substr($_field_sqls, 0, strpos($_field_sqls,':'));
                    $_field_sqls = trim(substr($_field_sqls, strpos($_field_sqls,':')+1), '()');
                    $groupfieldArr = explode('.', $_field_sqls);
                }
            }
            // echo "$field \n";
            $field_sql = preg_replace("/(?:#[0-9]+|\|.*)/", '', $field_str);
            if(in_array($field_str,$_f_arr2)){
                $field_form_name = $field;
            }else{
                $field_form_name = 'filter_'.$field;
            }

            $field_default = null; //默认值
            if(strpos($field_form_name, '|')){
                /*
                字段名,操作符,默认值
                preg_match("/([a-z0-9\_\#\s]+)([\%<>\=\*\+\!\s]*)(\|{0,1}.*)/i", $field_form_name, $arr);
                */
                list($field_form_name, $field_default) = explode('|', $field_form_name);
                $field_form_name = trim($field_form_name, '><=!%* ');
            }

            $field_form_name_var  = str_replace('#', '_', $field_form_name);//# --> _ 变量模式
            $field_form_name_sess = $field_form_name . '_' . $routeid;
            // echo "$field_form_name ---- $field_form_name_sess";
            if($isPost){
                $field_form_val = $this->post($field_form_name, $field_default);
                $field_form_val = is_array($field_form_val)?$field_form_val:trim($field_form_val);
                if(empty($field_form_val))$field_form_val = $field_default; //避免空字符串类似的情况
                $session->set($field_form_name_sess, $field_form_val);
            }else{
                $field_form_val = $session->get($field_form_name_sess, $field_default);
            }
            if(isset($groupfieldArr) && $groupfieldArr){
                // print_r($groupfieldArr);
                $groupfieldAll = $groupfieldOne = array();
                foreach($groupfieldArr as $_field_group){
                    if(false === strpos(trim($_field_group), ' ')){
                        //说明没有单独设操作符
                        $oprator = trim(preg_replace("/.+([\%<>\=\*\+\!\s].*?\d*?$)/", '$1', $_field_group));
                        if('in'==$oprator || '!in'==$oprator){
                            //如果含有in或!in的则切分为数组
                            $field_form_val = explode(',', $field_form_val);
                        }
                        if(strpos($field_str, ' ')){
                            $oprator = substr($field_str, strrpos($field_str,' '));
                            $_field_group .= $oprator;
                        }
                    }
                    // $this->dump($field_form_val);
                    if($field_form_val){//预防空
                        $groupfieldOne[$_orand][$_field_group] = $field_form_val;
                    }
                }
                $groupfieldAll[] = $groupfieldOne;
                // print_r($groupfieldOne);
                unset($groupfieldArr);
            }
            // echo "$field_form_name = $field_form_val <br/>\n";
            if(isset($groupfieldAll) && $groupfieldAll){
                foreach($groupfieldAll as $k=>$trr){
                    //20151021为了适应新版的where解析器
                    if(is_numeric($k)){
                        unset($groupfieldAll[$k]);
                        $groupfieldAll = array_merge($groupfieldAll, $trr);
                    }
                }
                $whArr = array_merge($whArr, $groupfieldAll);
                $agArr = array_merge($agArr, $whArr);
                unset($groupfieldAll);
            }else{
                if(!empty($field_form_val)){
                    $agArr[$field_sql] = $field_form_val;
                    if(!in_array($field_str,$_f_arr2)){//_f_arr2不是db条件
                        $whArr[$field_sql] = $field_form_val;
                    }
                }
            }
            if($tosmarty){
                $this->assign($field_form_name_var, $field_form_val);
            }
        }
        return $agArr;
    }
};

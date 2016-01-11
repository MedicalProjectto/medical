<?php
class KWeb extends CControllerApi {
    
    private $maxsize = 10000000;

    /**
    * desc: 文件上传(图片)
    * 文件命名规则:年(4).月(2).日(2).唯一ID(12)，年.月.日又是目录名(8位),共20位
    *
    * call: curl "http://api.medical.me/upload/web?token=0f5e4d24328d2a3a7cd7d5610985cc56" -F "Filedata=@d:/error.png"
    *
    */
    function actionEntry()
    {
        // $uploadLoc = $this->getLoc('_uploads');
        // $uploadUrl = $this->getUrl('_uploads');
        $uploadLoc = $this->getStaticLocation('upload');
        // $uploadUrl = $this->getUrl('_upload');
        $uploadUrl = '/static/upload';

        $php_path = dirname(__FILE__) . '/';
        $php_url  = dirname($_SERVER['PHP_SELF']) . '/';

        //文件保存目录路径
        $save_path = $uploadLoc. '/';
        //文件保存目录URL
        $save_url  = $uploadUrl. '/';
        //定义允许上传的文件扩展名
        if($prefix = $this->get('prefix')) {
            $save_path .= 'ad/';
            $save_url  .= 'ad/';
            if (!file_exists($save_path)) {
                mkdir($save_path);
            }
        }
        $ext_arr = array(
            'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico'),
            'flash' => array('swf', 'flv'),
            'media' => array('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'),
            'file' => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2','pdf'),
        );
        //最大文件大小
        $max_size = 5000000;

        $save_path = realpath($save_path) . '/';
        
        
        //PHP上传失败
        if (!empty($_FILES['Filedata']['error'])) {
            switch($_FILES['Filedata']['error']){
                case '1':
                    $error = '超过php.ini允许的大小。';
                    break;
                case '2':
                    $error = '超过表单允许的大小。';
                    break;
                case '3':
                    $error = '图片只有部分被上传。';
                    break;
                case '4':
                    $error = '请选择图片。';
                    break;
                case '6':
                    $error = '找不到临时目录。';
                    break;
                case '7':
                    $error = '写文件到硬盘出错。';
                    break;
                case '8':
                    $error = 'File upload stopped by extension。';
                    break;
                case '999':
                default:
                    $error = '未知错误。';
            }
            $this->error($error);
        }
        
        //有上传文件时
        if (empty($_FILES) === false) {
            //原文件名
            $file_name = $_FILES['Filedata']['name'];
            //服务器上临时文件名
            $tmp_name = $_FILES['Filedata']['tmp_name'];
            //文件大小
            $file_size = $_FILES['Filedata']['size'];
            //检查文件名
            if (!$file_name) {
                $this->error("请选择文件。");
            }
            //检查目录
            if (@is_dir($save_path) === false) {
                $this->error("上传目录不存在。");
            }
            //检查目录写权限
            if (@is_writable($save_path) === false) {
                $this->error("上传目录没有写权限。");
            }
            //检查是否已上传
            if (@is_uploaded_file($tmp_name) === false) {
                $this->error("上传失败。");
            }
            //检查文件大小
            if ($file_size > $max_size) {
                $this->error("上传文件大小超过限制。");
            }
            //获得文件扩展名
            $temp_arr = explode(".", $file_name);
            $file_ext = array_pop($temp_arr);
            $file_ext = trim($file_ext);
            $file_ext = strtolower($file_ext);

            //检查目录名
            $dir_name = empty($_GET['dir']) ? 'image' : trim($_GET['dir']);
            if(in_array($file_ext, $ext_arr['file'])){
                $dir_name = 'file';
            }
            if (empty($ext_arr[$dir_name])) {
                $this->error("目录名不正确。");
            }
            
            //检查扩展名
            if (in_array($file_ext, $ext_arr[$dir_name]) === false) {
                $this->error("上传文件扩展名是不允许的扩展名。\n只允许" . implode(",", $ext_arr[$dir_name]) . "格式。");
            }
            //创建文件夹
            if ($dir_name !== '') {
                $save_path .= $dir_name . "/";
                $save_url .= $dir_name . "/";
                if (!file_exists($save_path)) {
                    mkdir($save_path);
                }
            }
            $ymd   = date("Ymd");
            $year  = date("Y");
            $month = date("m");
            $day   = date("d");
            // $save_path .= $ymd . "/";
            // $save_url .= $ymd . "/";
            if('image' == $dir_name){ //图片才分年月日
                $save_url  .=  "$year/$month/$day/";

                $save_path .=  "$year/";
                if (!file_exists($save_path)) {
                    mkdir($save_path);
                }
                $save_path .=  "$month/";
                if (!file_exists($save_path)) {
                    mkdir($save_path);
                }
                $save_path .=  "$day/";
                if (!file_exists($save_path)) {
                    mkdir($save_path);
                }
            }
            //新文件名
            // $new_file_name = date("Ymd") . '' . CTool::getRid() . '.' . $file_ext;
            if(isset($_GET['fixedname'])){
                $fixedname = strlen($_GET['fixedname'])>1?$_GET['fixedname']:'tmp';
                $new_file_name = $fixedname . '.' . $file_ext;
            }else{
                // $new_file_name = date("Ymd") . '' . md5_file($tmp_name) . '.' . $file_ext;
                $new_file_name = CFun::crcU32($tmp_name) . '.' . $file_ext;
            }
            //移动文件
            $file_path = $save_path . $new_file_name;
            if(false === move_uploaded_file($tmp_name, $file_path)) {
                $this->error("上传文件失败。");
            }
            
            //是否裁剪
            $cut = isset($_GET['x'])?$_GET['x']:(isset($_POST['x'])?$_POST['x']:null);
            if($cut && strpos($cut, 'x') && 'image'==$dir_name){
                if(strpos($cut, 'x')){
                    $cutArr = explode('i', $cut);
                    foreach($cutArr as $_cut){
                        if(strpos($cut, 'x')){
                            list($cut_w, $cut_h) = explode('x', $_cut);
                            $file_cuted = CImg::cutImg($file_path, $cut_w, $cut_h);
                        }
                    }
                }else{
                    list($cut_w, $cut_h) = explode('x', $cut);
                    $file_cuted = CImg::cutImg($file_path, $cut_w, $cut_h);
                }
            }
            if(isset($file_cuted) && $file_cuted){
                $file_url = $save_url . basename($file_cuted);
            }else{
                @chmod($file_path, 0644);
                $file_url = $save_url . $new_file_name;
            }

            // header('Content-type: text/html; charset=UTF-8');
            // $json = new Services_JSON();
            // ob_clean();
            // echo json_encode(array('error' => 0, 'url' => $file_url));
            $this->response($file_url);
            exit;
        }
        $this->error('非法操作');
    }
    
};
/*
[Filedata] => Array
        (
            [name] => Array
                (
                    [0] => 134D3152QF-223638.jpg
                    [1] => 134D3152IF-1YF8.jpg
                    [2] => 134D3152K40-194492.jpg
                    [3] => 134D3152M50-20TO.jpg
                )

            [type] => Array
                (
                    [0] => image/jpeg
                    [1] => image/jpeg
                    [2] => image/jpeg
                    [3] => image/jpeg
                )

            [tmp_name] => Array
                (
                    [0] => C:\WINDOWS\Temp\php75.tmp
                    [1] => C:\WINDOWS\Temp\php76.tmp
                    [2] => C:\WINDOWS\Temp\php77.tmp
                    [3] => C:\WINDOWS\Temp\php78.tmp
                )

            [error] => Array
                (
                    [0] => 0
                    [1] => 0
                    [2] => 0
                    [3] => 0
                )

            [size] => Array
                (
                    [0] => 215711
                    [1] => 216623
                    [2] => 311171
                    [3] => 335270
                )

        )

)*/


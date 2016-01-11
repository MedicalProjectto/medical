<?php
class KDownload extends CController {
    

    function __construct(){
        // $this->getHtmlSidebar();
    }
    /**
    * desc: 文件上传(图片)
    * 文件命名规则:年(4).月(2).日(2).唯一ID(12)，年.月.日又是目录名(8位),共20位
    *
    *
    *
    */
    function actionEntry()
    {
        $file = base64_decode(rawurldecode($this->get('file')));
        $file = $this->getStaticLocation() . $file;
        $name = $this->get('name');

        if(!is_file($file)){
            exit('The file '.$name.'does not exists');
        }
        
        if(!$name){
            $file_name = basename($file);
        }else{
            $file_name = base64_decode(rawurldecode($name));
        }

        $file_size = filesize($file);
        
        header("Content-type: application/octet-stream"); 
        header("Accept-Ranges: bytes"); 
        header("Accept-Length:".$file_size); 
        header("Content-Disposition: attachment; filename=".$file_name); 
        echo file_get_contents($file);
    }
    
};

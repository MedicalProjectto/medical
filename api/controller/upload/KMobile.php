<?php
 
 class KMobile extends CControllerApi {
    /*
    * desc: 压缩上传
    *
    * call: curl "http://api.medical.me/upload/mobile?token=9d1f82e0addfbdf373ad3434575c6799" -d "Filedata=diuweofoeiwoe=="
    *
    */
    function actionEntry() {    
        $base64_string = $this->post('Filedata');
        if(!$base64_string){
            $this->error('数据不合法',40);
        }

        $savename = uniqid().'.jpeg';//localResizeIMG压缩后的图片都是jpeg格式

        $uploadLoc = $this->getStaticLocation('upload');
        $uploadUrl = '/static/upload/image';

        $save_path = $uploadLoc. '/image'; 
        if (!file_exists($uploadLoc)) {
            mkdir($uploadLoc);
        }
        if (!file_exists($save_path)) {
            mkdir($save_path);
        }
        $ymd   = date("Ymd");
        $year  = date("Y");
        $month = date("m");
        $day   = date("d");
        $uploadUrl  .=  '/' . "$year/$month/$day/";
        $save_path .=  '/' . "$year/";
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

        $save_path .= '/' . $savename; 
        $uploadUrl .= $savename;
        $image = $this->base64_to_img( $base64_string, $save_path);

        if($image){
            // echo '{"status":1,"content":"上传成功","url":"'.$uploadUrl.'"}';
            $this->response($uploadUrl);
        }else{
            // echo '{"status":0,"content":"上传失败"}';
            $this->error('上传失败', 22);
        } 
    }

    function base64_to_img( $base64_string, $output_file ) {
        $ifp = fopen( $output_file, "wb" ); 
        fwrite( $ifp, base64_decode( $base64_string) ); 
        fclose( $ifp ); 
        return( $output_file ); 
    }
}

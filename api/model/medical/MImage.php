<?php
/**
 * desc: 图片管理
 *
 *
 *
*/

class MImage extends CHookModel {

    private $tImage  = 'images';     //图片表

    /*
    * desc: 获取其中一张图片信息
    *
    *
    */
    public function getImages($imageids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $imageids){
            if(is_array($imageids)){
                $whArr = array_merge(array('id in'=>$imageids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$imageids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        /***************************join end********************/
        /***************************only_data*******************/
        $exArr['only_data'] = isset($exArr['only_data'])?$exArr['only_data']:true;
        $exArr['limit']     = isset($exArr['limit'])?$exArr['limit']:100;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tImage, $whArr, $exArr);
        if(!$dataArr)return false;
        //业务处理...
        return $dataArr;
    }
    /*
    * desc: 获取一条图片记录
    *
    *
    */
    public function getImage($imageid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $imageid){
            $whArr = array_merge(array('id'=>$imageid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getImages(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }
    /*
    * desc: 压缩图片
    *
    *
    */
    public function compressImage($src)
    {
        $cmd = $this->getConfig('sh_compress');
        CFun::Process($cmd);
    }
    /*
    * desc: 添加一个图片
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               image   --- 图片信息
    *               )
    */
    public function addImage($postArr, $iscompress=true)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'image'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['src'])) {
            $retArr['message'] = '图片地址不存在';
            return $retArr;
        }
        $postArr['ctime'] = date("Y-m-d H:i:s");
        //数据检查==================================end
        $id = $this->addAtom($this->tImage, $postArr);
        if($id){
            $imageInfo = $this->getImage($id);
            if($iscompress){//压缩图片
                // $src_image = rtrim($this->getDataLocation(),'/').$imageInfo['src'];
                // $this->compressImage($src_image);
            }
            $retArr['status']  = 1;
            $retArr['image']   = $imageInfo;
            $retArr['message'] = "添加图片成功";
        }
        return $retArr;
    }
    /*
    * desc: 更新一个图片
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               image --- 图片信息
    *               )
    *
    */
    public function updateImage($imageid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'image'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr) || !$imageid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getImage($imageid,null,null,$type);
        if(!$old){
            $retArr['message'] = '图片不存在';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");
        //数据检查==================================end

        $ok = $this->updateData($this->tImage, $postArr, $imageid);
        if($ok){
            $imageInfo = $this->getImage($imageid);
            $retArr['image'] = $imageInfo;
            $retArr['status']   = 1;
            $retArr['message']  = '更新成功';
        }else{
            $retArr['message']  = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
    /*
    * desc: 切底删除图片
    * 步骤: 1, 删除image,image_profile表中的数据
    *       2, 将该用户的role设置成10(普通用户)
    *
    */
    public function dropImage($imageid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'image'=>null);
        //数据检查=====================================
        if(!$imageid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id');
        $old = $this->getImage($imageid, null, $exArr);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        //数据检查==================================end

        $ok = $this->deleteData($this->tImage, $imageid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
};


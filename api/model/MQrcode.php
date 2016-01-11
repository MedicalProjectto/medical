<?php
/**
 * desc: 二维码相关
 *
 *
 *
*/

class MQrcode extends CHookModel {
    
    private $dbname   = null;
    private $tQrcode  = 'qrcode';

    public function getQrcodes($pluginids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $pluginids){
            if(is_array($pluginids)){
                $whArr = array_merge(array('id in'=>$pluginids), $whArr); 
            }else{
                $whArr = array_merge(array('id'=>$pluginids), $whArr);  //保证id在前
            }
        }
        $exArr['only_data'] = isset($exArr['only_data'])?$exArr['only_data']:false;
        $join_plugin = isset($exArr['join_plugin'])?$exArr['join_plugin']:false;
        if(!isset($exArr['join']))$exArr['join']=array();
        if(isset($exArr['join_store']) && $exArr['join_store']){
            $exArr['join']['store'] = 'qrid:id';
        }
        $rowArr = $this->getMore($this->tQrcode, $whArr, $exArr, $this->dbname);
        if(!$rowArr)return false;

        if($exArr['only_data'] && isset($rowArr['data'])){
            return $rowArr['data'];
        }
        return $rowArr;
    }

    public function getQrcode($qrid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $qrid){
            $whArr = array_merge(array('id'=>$qrid), $whArr);  //保证id在前
        }
        return $this->getAtom($this->tQrcode, $whArr, $exArr);
    }

    public function addQrcode($postArr)
    {
        $postArr = $this->removeArrayNull($postArr);
        $retArr = array('status'=>0, 'message'=>'', 'user'=>null);
        if(empty($postArr) || !isset($postArr['url']) ) {
            //email,mobile,loginname不能同时为空
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $postArr['url32'] = sprintf("%u",crc32($postArr['url']));
        $postArr['ctime'] = date("Y-m-m H:i:s");

        $old = $this->getAtom($this->tQrcode, array('url32'=>$postArr['url32']), null, $this->dbname);
        if($old){
            $retArr['message'] = '不能重复添加';
            $retArr['qrcode']  = $old;
            return $retArr;
        }
        $id = $this->addAtom($this->tQrcode, $postArr, $this->dbname);
        if($id){
            $retArr['qrcode']  = $this->getAtom($this->tQrcode, $id, null, $this->dbname);
            $retArr['status']  = 1;
            $retArr['message'] = '添加成功';
        }else{
            // $retArr['message'] = $this->error = $dbAd->getError();
            $retArr['message'] = '系统错误，请稍候再试';
        }
        return $retArr;
    }
    /*
    * desc: 修改组件信息
    *
    * return: array( 
    *               status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               user    --- 新广告信息
    *               ) 
    */
    public function updateQrcode($id, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'数据不合法', 'user'=>null);
        $postArr = $this->removeArrayNull($postArr);
        if(empty($postArr)) return $retArr;
        if(empty($id)) return $retArr;

        $old = $this->getAtom($this->tQrcode, array('id'=>$id), null, $this->dbname);
        if(!$old){
            $retArr['message'] = '修改的组件不存在';
            return $retArr;
        }
        if(isset($postArr['url']) && $old['url'] != $postArr['url']){//说明修改了组件
            $postArr['url32'] = sprintf("%u",crc32($postArr['url']));
            $old = $this->getAtom($this->tQrcode, array('url32'=>$postArr['url32']), null, $this->dbname);
            if($old){
                $retArr['message'] = '该二维码已存在';
                $retArr['qrcode'] = $old;
                return $retArr;
            }
        }
        $whArr = array('id'=>$id);
        // $this->dump($postArr);exit;
        $ok = $this->updateData($this->tQrcode, $postArr, $whArr, $this->dbname);
        if(false !== $ok){
            $plugin = $this->getAtom($this->tQrcode, $old['id'], null, $this->dbname);
            $retArr['plugin'] = $plugin;
            $retArr['status'] = 1;
            $retArr['message'] = '修改成功';
        }else{
            $retArr['message'] = '修改失败';
        }
        return $retArr;
    }
    public function dropQrcode($id)
    {
        if(empty($id)) return false;
        $whArr['id'] = $id;
        $ok = $this->deleteData($this->tQrcode, $whArr, 1, $this->dbname);
        return (false!==$ok)?true:false;
    }
    
};
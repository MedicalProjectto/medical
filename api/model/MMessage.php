<?php
/**
 * desc: 消息
 *
 *
 *
*/
class MMessage extends CHookModel {
    
    private $tMessage  = 'message';

    public function getMessages($messageids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $messageids){
            if(is_array($messageids)){
                $whArr = array_merge(array('id in'=>$messageids), $whArr); 
            }else{
                $whArr = array_merge(array('id'=>$messageids), $whArr);  //保证id在前
            }
        }
        return $this->getMore($this->tMessage, $whArr, $exArr);
    }

    public function getMessage($messageid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $messageid){
            $whArr = array_merge(array('id'=>$messageid), $whArr);  //保证id在前
        }
        return $this->getAtom($this->tMessage, $whArr, $exArr);
    }
    /*
    * desc: 获取相关消息(最后一条)
    *
    */
    public function getLastMessages($userid, $page=1)
    {
        $limit = 20;
        $start = (intval($page)-1)*$limit;
        $userid = intval($userid);
        $sql = "select id,userid,targetid friendid,content msg,ctime,max(ctime) lastime from message where type=0 and removed>-1 and userid={$userid} group by targetid union select id,userid,targetid friendid,content msg,ctime,max(ctime) lastime from message where type=0 and removed>-1 and targetid={$userid} group by userid order by id desc limit {$start},{$limit}";
        return $this->Execute($sql);
    }

    public function addMessage($postArr)
    {
        $postArr = $this->removeArrayNull($postArr);
        $retArr = array('status'=>0, 'message'=>'', 'message'=>null);
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $postArr['ctime'] = date("Y-m-d H:i:s");

        $id = $this->addAtom($this->tMessage, $postArr);
        if($id){
            $retArr['message']  = $this->getAtom($this->tMessage, $id);
            $retArr['status']  = 1;
            $retArr['message'] = '添加成功';
        }else{
            $retArr['message'] = '系统错误，请稍候再试';
        }
        return $retArr;
    }
    /*
    * desc: 修改消息信息
    *
    * return: array( 
    *               status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               message   --- 新广告信息
    *               ) 
    */
    public function updateMessage($id, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'数据不合法', 'message'=>null);
        $postArr = $this->removeArrayNull($postArr);
        if(empty($postArr)) return $retArr;
        if(empty($id)) return $retArr;

        $old = $this->getAtom($this->tMessage, array('id'=>$id), null);
        if(!$old){
            $retArr['message'] = '消息不存在';
            return $retArr;
        }
        $ok = $this->updateData($this->tMessage, $postArr, $id);
        if(false !== $ok){
            $retArr['status'] = 1;
            $retArr['message'] = '修改成功';
        }else{
            $retArr['message'] = '修改失败';
        }
        return $retArr;
    }
    public function dropMessage($id, $userid=null)
    {
        if(empty($id)) return false;
        if($userid){
            $old = $this->getMessage($id);
            if(!$old)return false;
            if($userid != intval($old['userid'])){
                return false;
            }
        }
        $ok = $this->deleteData($this->tMessage, $id, 1);
        return (false!==$ok)?true:false;
    }

    //===============================================================
    /*
    * desc: 发送消息
    *
    */
    public function send($userid=0, $targetids=null, $content=null, $postArr=array())
    {
        $postArr['userid']   = $userid;
        $postArr['content']  = $content;
        if(!empty($postArr['url'])){
            $postArr['label'] = empty($postArr['label'])?'前往':$postArr['label'];
        }
        if(is_array($targetids) && count($targetids)>0){
            //多条
            foreach($targetids as $_tid){
                $postArr['targetid'] = $_tid;
                $retArr = $this->addMessage($postArr);
            }
        }else{
            $postArr['targetid'] = $targetids;
            $retArr = $this->addMessage($postArr);
        }
        
        return 1==intval($retArr['status'])?true:false;
    }

    public function recv($userid=0)
    {
        $whArr = array(
            'and' => array(
                'or'  => array(
                    array('targetid'=>$userid),
                    array('targetid'=>0),
                ),
                // array(
                //     't>'=>1980
                // )
            )
        );
        $exArr = array(
            'limit' => 20,
        );
        return $this->getMessages(null, $whArr, $exArr);
    }

};
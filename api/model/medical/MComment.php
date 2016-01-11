<?php
/**
 * desc: 评论
 *
 *
 *
*/

class MComment extends CHookModel {

    private $tComment  = 'comments';
    /*
    * desc: 获取多条评论记录(加了个s是为了区分comment)
    *
    *
    */
    public function getComments($commentids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $commentids){
            if(is_array($commentids)){
                $whArr = array_merge(array('id in'=>$commentids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$commentids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated     = isset($exArr['aggregated'])?$exArr['aggregated']:false;    //标明是否为聚合查询
        $join_store     = isset($exArr['join_store'])?$exArr['join_store']:true;     //是否要查询商家(store)信息
        $join_user      = isset($exArr['join_user'])?$exArr['join_user']:false;  //join详情
        // $swap_key    已更名为keyfield//标明是否为聚合查询
        if(!$aggregated){
        }
        /***************************join end********************/
        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tComment, $whArr, $exArr);
        if(!$dataArr)return null;
        //业务处理...
        if(!$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_user){
                $_uid_arr1 = $this->getArrayColumn($rowArr,'userid');//施工商
                $MUser = $this->LoadApiModelBuilding('user');
                $userArr = $MUser->getUsers($_uid_arr1,null,array('limit'=>count($_uid_arr1),'only_data'=>true,'fields'=>'id,mobile,email,username,truename','join_profile'=>false));
                $rowArr = $this->joinToArray($rowArr, $userArr, 'userid:id', 'user');
                // $this->dump($rowArr);
            }
        }
        return $dataArr;
    }
    /*
    * desc: 获取一条评论记录
    *
    *
    */
    public function getComment($commentid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $commentid){
            $whArr = array_merge(array('id'=>$commentid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getComments(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }
    /*
    * desc: 添加一个评论
    *
    *
    *return: array( status   --- 状态(1:成功,0:失败)
    *               message  --- 提示信息
    *               comment  --- 评论信息
    *               )
    */
    public function addComment($postArr)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'comment'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['userid'])) {
            $retArr['message'] = '没有用户';
            return $retArr;
        }
        $postArr['ctime'] = date("Y-m-d H:i:s");
        $postArr['hits']  = isset($postArr['hits'])?$postArr['hits']:1;
        //数据检查==================================end
        if(isset($postArr['flag'])){
            $old = $this->getComment(null,array('flag'=>$postArr['flag']));
            if($old){
                $new_hits = intval($old['hits']) + intval($postArr['hits']);
                return $this->updateComment($old['id'], array('hits'=>$new_hits));
            }
        }
        $id = $this->addAtom($this->tComment, $postArr, array('log'=>'comment'));
        if($id){
            $comment = $this->getComment($id);
            $retArr['status']   = 1;
            $retArr['comment'] = $comment;
            $retArr['message']  = "添加评论成功";
        }else{
            // $retArr['message'] = null;
        }
        return $retArr;
    }
    /*
    * desc: 更新一个评论
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               comment --- 评论信息
    *               )
    *
    */
    public function updateComment($commentid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'comment'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr) || !$commentid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getComment($commentid);
        if(!$old){
            $retArr['message'] = '评论不存在';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");
        //数据检查==================================end

        $ok = $this->updateData($this->tComment, $postArr, $commentid);
        if($ok){
            $commentInfo = $this->getComment($commentid);
            $retArr['comment']  = $commentInfo;
            $retArr['status']   = 1;
            $retArr['message']  = '更新成功';
        }else{
            $retArr['message']  = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
    /*
    * desc: 切底删除评论
    * 步骤: 1, 删除comment,comment_profile表中的数据
    *       2, 将该用户的role设置成10(普通用户)
    *
    */
    public function dropComment($commentid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'comment'=>null);
        //数据检查=====================================
        if(!$commentid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id,storeid');
        $old = $this->getComment($commentid);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        //数据检查==================================end
        $ok = $this->deleteData($this->tComment, $commentid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
};

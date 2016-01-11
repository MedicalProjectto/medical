<?php
/**
 * desc: 消息中心
 *
 *
 *
*/
class KMessage extends CControllerApi {

    /*
    * desc: 获取当前用户的消息
    * call: curl "http://api.medical.me/message/recv?token=e30bf504913669e95d04548fde00dc10"
    *
    */
    function actionRecv()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 20);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
            'order' => 'id desc',
        );

        if(1) {
            $date = $this->get('date');
            list($date_start, $date_end) = CTime::DeltaMonths(-1);
            // echo "$date_start, $date_end";
            $date = $date?$date:$date_start;
            $targetid = $this->get('targetid');
            $targetid = $targetid?$targetid:$userid;
            $mtype  = $this->get('type');//消息类型
            $whArr = array(
                'type' => $mtype,
                'removed>'=>-1,
                'ctime>' => $date,
            );
            if(10 == $mtype){

            }else{
                $whArr['or'] = array(
                    'userid'   => 0, //系统发的
                    'targetid in' => array($targetid)
                );
            }
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MMessage = $this->LoadApiModel('message');
            $messArr  = $MMessage->getMessages(null,$whArr,$exArr);
            // print_r($messArr);
            if($messArr){
                // $last = $MMessage->getMessage(null, array('or'=>array('userid'=>$userid,'targetid'=>$userid), array('order'=>'id desc')));
                // $messArr['last'] = $last;
                $MUser = $this->LoadApiModelMedical('user');
                $uid_arr = $this->getArrayColumn($messArr['data'], 'userid');
                $userArr = $MUser->getUsers($uid_arr, null, array('limit'=>count($uid_arr),'only_data'=>true,'fields'=>'id,username,idcard,mobile'));
                $messArr['data'] = $this->joinToArray($messArr['data'], $userArr, 'userid:id', 'user');
                $this->response($messArr);
            }
        }
        $this->error('没有找到任何新病人信息');
    }
    /*
    * desc: 获取与某人的聊天记录
    * call: curl "http://api.medical.me/message/chat?token=43241dd3e9de84bdf3b28b0c80fd5939&id=16"
    *
    */
    function actionChat()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 20);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
            'order' => 'id desc',
            'fields' => 'id,userid,targetid friendid,content msg,ctime',
        );//lastime

        if(1) {
            $MMessage = $this->LoadApiModel('message');
            $date = $this->get('date');
            list($date_start, $date_end) = CTime::DeltaMonths(-1);
            // echo "$date_start, $date_end";
            $date = $date?$date:$date_start;
            $friendid = $this->get('id');
            if($friendid){//我与指定好友的消息(也就是我发给别人的)
                $whArr = array(
                    'type' => 0,
                    'targetid in' => array($friendid, $userid),
                    // 'ctime>' => $date,
                    'removed>'=>-1,
                );
                $messArr  = $MMessage->getMessages(null,$whArr,$exArr);
            }else{//别人发给我的
                $whArr = array(
                    'type' => 0,
                    'or' => array(
                        'userid' => $userid,
                        'targetid' => $userid,
                    ),
                    // 'ctime>' => $date,
                    'removed>'=>-1,
                );
                // $exArr['fields'] .= ',max(ctime) lastime';
                // $exArr['group']   = 'userid,targetid';
                $messArr = $MMessage->getLastMessages($userid, $page);
                // print_r($messArr);
                $messArr = array('data'=>$messArr, 'total'=>count($messArr));
            }
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            
            // print_r($messArr);
            if(false !== $messArr){
                if(isset($messArr['data'])){
                    $MUser = $this->LoadApiModelMedical('user');
                    $uid_fild = $friendid?'userid':'friendid';
                    $fid_arr = $this->getArrayColumn($messArr['data'], $uid_fild);
                    $userArr = $MUser->getUsers($fid_arr, null, array('limit'=>count($fid_arr),'only_data'=>true,'fields'=>'id,username,idcard,mobile'));
                    if($friendid){
                        $messArr['data'] = $this->joinToArray($messArr['data'], $userArr, 'userid:id', 'user');
                    }else{
                        $messArr['data'] = $this->joinToArray($messArr['data'], $userArr, 'friendid:id', 'user');
                    }
                    
                }
                $this->response($messArr);
            }
        }
        $this->error('没有任何消息');
    }
    /*
    * desc: 发送消息
    * call: curl -d "content=msg1&targetid[]=16" http://api.medical.me/message/send?token=b621fdedcb43146efa71c613429bfe50
    *
    */
    function actionSend()
    {
        $userid  = $this->userid;

        if($this->isPost()){
            $MMessage  = $this->LoadApiModel('message');

            $postArr   = $this->posts('type,url,label');
            $content   = $this->post('content');
            $targetids = $this->post('targetid');
            if(10 == intval($postArr['type'])){
                if(empty($targetids) || empty($targetid['targetid'])){ //系统公告
                    $ok = $MMessage->send($userid, 0, $content, $postArr);
                    if($ok){
                        $this->response('发送成功');
                    }else{
                        $this->error('发送不合法');
                    }
                }
                $MProject = $this->LoadApiModelMedical('project');
                $projectArr = $MProject->getProjects($targetids, null, array('aggregated'=>true,'only_data'=>true,'limit'=>count($targetids),'fields'=>'id','keyas'=>'id'));
                if(!$projectArr){
                    $this->error('不合法的项目');
                }
                $pid_arr = array_keys($projectArr);
                $targetids = array_intersect($pid_arr, $targetids);
                // print_r($targetids);
                if(!$targetids){
                    $this->error('发送不合法');
                }
            }else{
                if(!empty($targetids)){
                    $MUser = $this->LoadApiModelMedical('user');
                    $userArr = $MUser->getUsers($targetids, null, array('aggregated'=>true,'only_data'=>true,'limit'=>count($targetids),'fields'=>'id','keyas'=>'id'));
                    if(!$userArr){
                        $this->error('发送失败:不存在的用户');
                    }
                    $uid_arr = array_keys($userArr);
                    $targetids = array_intersect($uid_arr, $targetids);
                    if(!$targetids){
                        $this->error('发送失败:不存在的用户');
                    }
                }
            }
            $ok        = $MMessage->send($userid, $targetids, $content, $postArr);
            if($ok) {
                $this->message('发送成功');
            }
            $this->error('发送失败:服务器错误');
        }
        $this->error('禁止操作');
    }

    // 获取提醒数
    function actionCount() {
        $jArr = array('total'=>0);
        $session = $this->getSession();
        $userid = $session->get('userid_sess');

        $MMessage = $this->LoadApiModel('message');
        $msg = $MMessage->getMessages(null, array('uid_to'=>$userid, 'status'=>0, 'removed>'=>-1));
        if ($msg) {
            $jArr['total'] = $msg['total'];
        }

        exit(json_encode($jArr));
    }

    /*
    * desc: 删除消息
    * call: curl -d "id=2" http://api.medical.me/message/drop?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;

        if($id = $this->post('id')){
            $status = $this->post('status');
            $MMessage = $this->LoadApiModel('message');
            $ok = $MMessage->dropMessage($id, $userid);
            if($ok){
                $this->message('消息已删除');
            }
            $this->error('系统错误');
        }
        $this->error('请求不合法');
    }

    /*
    * desc: 软删除消息
    * call: curl -d "id=1" http://api.medical.me/message/remove?token=3d8d0bf9bee3340fb2930f430b0bdbb3
    *
    */
    function actionRemove()
    {
        $userid = $this->userid;

        if($id = $this->post('id')){
            $status = $this->post('status');
            $MMessage = $this->LoadApiModel('message');
            $ok = $MMessage->updateMessage($id, array('removed'=>-1));
            if($ok){
                $this->message('消息已删除');
            }
            $this->error('系统错误');
        }
        $this->error('请求不合法');
    }
};

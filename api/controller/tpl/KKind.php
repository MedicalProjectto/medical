<?php
/**
 * 模板分类相关
 *
 *
 *
 *
*/
class KKind extends CControllerApi{
 
    /*
    * desc: 获取一个医院的所有模板分类
    * call: curl http://api.medical.me/tpl/kind/list?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionList()
    {
        $userid = $this->userid;//管理员的userid

        if(1) {
            $MKind = $this->LoadApiModel('tree')->Type('kind');
            $kindArr = $MKind->getNodes(null,null,array('limit'=>100,'only_data'=>true,'fields'=>'id,name'));
            // var_dump($kindArr);
            if($kindArr){
                $this->response($kindArr);
            }
        }
        $this->error('未找到模板分类');
    }
    /*
    * desc: 后台添加分类
    * call: curl -d "name=c1" http://api.medical.me/tpl/kind/add?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionAdd()
    {
        $userid = $this->userid;

        $postArr = $this->posts('parentid,name');

        if($this->isPost()) {
            $MKind = $this->LoadApiModel('tree')->Type('kind');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            // print_r($user);
            if($role >= 10){
                $old = $MKind->getNode(null,array('name'=>$postArr['name']));
                if($old){
                    $this->error('分类已存在');
                }
                $retArr = $MKind->addNode($postArr);
            }else{
                $this->error('无权限操作');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $kind = $retArr['node'];
                $this->response($kind);
            }
        }
        $this->error('添加分类失败');
    }
 
    /*
    * desc: 删除分类
    * call: curl -d "id=22" http://api.medical.me/tpl/kind/drop?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($kindid=$this->post('id')){
            $MKind = $this->LoadApiModel('tree')->Type('kind');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 10){
                    $ok = $MKind->dropNode($kindid);
                    if($ok){
                        $this->message('分类已删除');
                    }
                }
            }
        }
        $this->error('无权限操作');
    }
    /*
    * desc: 设置分类
    * call: curl http://api.medical.me/term/user/profile?token=7e9afe9599bd8a9f4fcc6fe220b9652b [-XPOST -d "field=value"]
    *
    */
    function actionSet()
    {
        //判断是否登入
        $userid = $this->userid;

        $MKind = $this->LoadApiModel('tree')->Type('kind');
        if($this->isPost() && $kindid  = $this->post('kindid')){
            //更新profile
            $postArr = $this->posts('parentid,name');
            $MUser   = $this->LoadApiModelMedical('user');
            $retArr  = $MUser->updateNode($kindid, $postArr);
            if(!$retArr['status']){
                $this->error('设置资料失败', 200);
            }
        }
        $kindid = $this->get('kindid');
        $info = $MKind->getCate($kindid);
        if($info){
            $this->response(1, $info, '获取资料成功');
        }
        $this->error('获取资料失败', 500);
        // print_r($info);
    }
};

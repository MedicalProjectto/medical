<?php
/**
 * 模板的指标的分片
 *
 *
 *
 *
*/
class KSharp extends CControllerApi{
 
    /*
    * desc: 获取一个医院的所有模板分类
    * call: curl "http://api.medical.me/tpl/sharp/list?token=b5ad52a3b0129e1ff7b51a6f9e627777&tplid=120"
    *
    */
    function actionList()
    {
        $userid = $this->userid;//管理员的userid

        if($tplid = $this->get('tplid')) {
            $MSharp = $this->LoadApiModel('tree')->Type('sharp');
            $sharpArr = $MSharp->getNodes(null,array('tplid'=>$tplid),array('limit'=>50,'only_data'=>true,'fields'=>'id,name'));
            // var_dump($sharpArr);
            if($sharpArr){
                $this->response($sharpArr);
            }
        }
        $this->error('未找到模板分类');
    }
    /*
    * desc: 后台添加分类
    * call: curl -d "name=c1&tplid=9" http://api.medical.me/tpl/sharp/add?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionAdd()
    {
        $userid = $this->userid;

        $postArr = $this->posts('parentid,tplid,name');

        if($this->isPost()) {
            $MSharp = $this->LoadApiModel('tree')->Type('sharp');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            // print_r($user);
            if($role >= 10){
                $old = $MSharp->getNode(null,array('tplid'=>$postArr['tplid'],'name'=>$postArr['name']));
                if($old){
                    $this->error('分类已存在');
                }
                $retArr = $MSharp->addNode($postArr);
            }else{
                $this->error('无权限操作');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $sharp = $retArr['node'];
                $this->response($sharp);
            }
        }
        $this->error('添加分类失败');
    }
 
    /*
    * desc: 删除分类
    * call: curl -d "id=5" http://api.medical.me/tpl/sharp/drop?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($sharpid=$this->post('id')){
            $MSharp = $this->LoadApiModel('tree')->Type('sharp');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 10){
                    $ok = $MSharp->dropNode($sharpid);
                    if($ok){
                        $this->message('分片已删除');
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

        $MSharp = $this->LoadApiModel('tree')->Type('sharp');
        if($this->isPost() && $sharpid  = $this->post('sharpid')){
            //更新profile
            $postArr = $this->posts('parentid,name');
            $MUser   = $this->LoadApiModelMedical('user');
            $retArr  = $MUser->updateNode($sharpid, $postArr);
            if(!$retArr['status']){
                $this->error('设置资料失败', 200);
            }
        }
        $sharpid = $this->get('sharpid');
        $info = $MSharp->getCate($sharpid);
        if($info){
            $this->response(1, $info, '获取资料成功');
        }
        $this->error('获取资料失败', 500);
        // print_r($info);
    }
};

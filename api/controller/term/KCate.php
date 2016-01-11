<?php
/**
 * 病例分类相关
 *
 *
 *
 *
*/
class KCate extends CControllerApi{
 
    /*
    * desc: 获取一个医院的所有分类
    * call: curl -d "hospitalid=120" http://api.medical.me/term/cate/all?token=7e9afe9599bd8a9f4fcc6fe220b9652b
    *@mobile   --- 手机号
    *@idcard   --- 身份证号
    *
    */
    function actionAll()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $hospitalid = $this->post('hospitalid')) {
            $MCate = $this->LoadApiModel('tree')->Type('cate');
            $cateArr = $MCate->getNodes(null,array('hospitalid'=>$hospitalid),array('limit'=>300,'only_data'=>true,'fields'=>'id,name'));
            // var_dump($cateArr);
            if($cateArr){
                $this->response($cateArr, '获取分类成功');
            }
        }
        $this->error('获取分类失败');
    }
    /*
    * desc: 后台添加分类
    * call: curl -d "name=c1&hospitalid=120" http://api.medical.me/term/cate/add?token=7e9afe9599bd8a9f4fcc6fe220b9652b
    *@name       --- 分类名称
    *@hospitalid --- 医院
    *
    */
    function actionAdd()
    {
        $userid = $this->userid;//管理员的userid

        $postArr = $this->posts('parentid,name,hospitalid');

        if($this->isPost()) {
            $postArr['enabled'] = 1;
            $MCate = $this->LoadApiModel('tree')->Type('cate');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            // print_r($user);
            if($role >= 10){
                $old = $MCate->getNode(null,array('hospitalid'=>$postArr['hospitalid'],'name'=>$postArr['name']));
                if($old){
                    $this->error('分类已存在');
                }
                $retArr = $MCate->addNode($postArr);
            }else{
                $this->error('无权限操作');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $cate = $retArr['node'];
                $this->response(1, $cate, '添加分类成功');
            }
        }
        $this->error('添加分类失败');
    }
 
    /*
    * desc: 删除分类
    * call: curl -d "cateid=1" http://api.medical.me/term/cate/drop?token=7e9afe9599bd8a9f4fcc6fe220b9652b
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $cateid=$this->post('cateid')){
            $MCate = $this->LoadApiModel('tree')->Type('cate');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 10){
                    $ok = $MCate->dropNode($cateid);
                    if($ok){
                        $this->response(1, '分类已删除');
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

        $MCate = $this->LoadApiModel('tree')->Type('cate');
        if($this->isPost() && $cateid  = $this->post('cateid')){
            //更新profile
            $postArr = $this->posts('parentid,name');
            $MUser   = $this->LoadApiModelMedical('user');
            $retArr  = $MUser->updateNode($cateid, $postArr);
            if(!$retArr['status']){
                $this->error('设置资料失败', 200);
            }
        }
        $cateid = $this->get('cateid');
        $info = $MCate->getCate($cateid);
        if($info){
            $this->response(1, $info, '获取资料成功');
        }
        $this->error('获取资料失败', 500);
        // print_r($info);
    }
};

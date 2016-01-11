<?php
/**
 * 模板相关
 *
 *
 *
 *
*/
class KTpl extends CControllerApi{

    /*
    * desc: 获取项目列表
    * call: curl http://api.medical.me/tpl/list?token=121fdde8382f0545b089cc8aae189e5e
    *
    */
    function actionList()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 20);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
            'join_detail' => true,
        );

        if(1) {
            $whArr = array(
                'kindid'    => $this->get('kindid'),
                'name%'     => $this->get('name'),
            );
            CFun::removeArrayNull($whArr);

            $MTpl = $this->LoadApiModelMedical('tpl');
            $tplArr = $MTpl->getTpls(null, $whArr, $exArr);
            if($tplArr){
                $this->response($tplArr);
            }
        }
        $this->error('没有找到任何模板');
    }

    /*
    * desc: 获取一个模板的详情
    * call: curl "http://api.medical.me/tpl/detail?token=fc5a02241b3e84d7d58d7342b3589d00&id=9"
    *
    */
    function actionDetail()
    {
        $userid = $this->userid;//管理员的userid

        if($tplid = $this->get('id')) {
            $MTpl = $this->LoadApiModelMedical('tpl');
            $tpl  = $MTpl->getTpl($tplid,null,array('join_detail'=>true,'join_term'=>true,'join_sharp'=>true));
            // print_r($tpl);
            if($tpl){
                $this->response($tpl);
            }
        }
        $this->error('获取模板失败');
    }
    /*
    * desc: 创建模板
    * call: curl -d "name=tpl1&remark=tt&sharpid[]=1&sharpid[]=1&termid[]=1&termid[]=2" http://api.medical.me/tpl/create?token=fc5a02241b3e84d7d58d7342b3589d00
    *
    */
    function actionCreate()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost()) {
            $postArr = $this->posts('name,period,editbypat,remark');

            $details = $this->posts('sharpid,termid');

            $MTpl  = $this->LoadApiModelMedical('tpl');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);

            $postArr['hospitalid'] = $user['hospitalid'];//创建者的医院
            $postArr['userid']     = $userid;
            
            if($role >= 10){
                $retArr = $MTpl->addTpl($postArr, $details);
            }else{
                $this->error('操作无权限');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $tpl = $retArr['tpl'];
                $this->response($tpl);
            }else{
                $message = isset($retArr['message'])?$retArr['message']:'创建模板失败';
                $this->error($message);
            }
        }
        $this->error('添加模板失败');
    }
 
    /*
    * desc: 删除模板
    * call: curl -d "id=7" http://api.medical.me/tpl/drop?token=386b710e50c91f9b056a1218a30078fe
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $tplid=$this->post('id')){
            $MTpl = $this->LoadApiModelMedical('tpl');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 80){
                    $ok = $MTpl->dropTpl($tplid);
                    if($ok){
                        $this->response('模板已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
    /*
    * desc: 更新模板/值
    * call: curl -d "remark=tt2&id[]=1&id[]=2&termid[]=1&termid[]=3" "http://api.medical.me/tpl/change?token=ca2b916cb63d382812da410c8b9fed7f&id=9"
    * call: curl -d "id[]=23&editbypat[]=1" "http://115.29.176.160/tpl/change?token=702b26b23316e5e3b360833a696be34d&id=10"
    *
    */
    function actionChange()
    {
        //判断是否登入
        $userid = $this->userid;

        $MTpl  = $this->LoadApiModelMedical('tpl');
        $tplid = $this->get('id');

        if($this->isPost() && $tplid){
            //更新profile
            $postArr = $this->posts('name,period,freqs,editbypat,remark');
            $details = $this->posts('id,termid,editbypat');

            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $hospitalid = $user['hospitalid'];

            $old = $MTpl->getTpl($tplid); //修改前的模板
            if(!$old /*|| $hospitalid != $old['hospitalid']*/){
                $this->error('操作无权限', 403);
            }
            $retArr = $MTpl->updateTpl($tplid, $postArr, $details);
            if(!$retArr['status']){
                $this->error('设置资料失败');
            }
        }
        $info = $MTpl->getTpl($tplid);
        if($info){
            $this->response($info);
        }
        $this->error('模板变换异常');
    }
};

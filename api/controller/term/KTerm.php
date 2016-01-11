<?php
/**
 * 指标相关
 *
 *
 *
 *
*/
class KTerm extends CControllerApi{

    /*
    * desc: 指标列表(ajax)
    * curl "http://api.medical.me/term/list?token=b658efc03bbf5e9d8195c18bf2619ca8"
    *
    */
    public function actionList()
    {
        $userid = $this->userid;    //管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 20);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
            'join_val' => true,
            'join_cate' => true,
        );

        if(1) {
            $whArr = array(
                'name%'      => $this->get('name'),
                'userid'     => $this->get('userid'),
                'hospitalid' => $this->get('hospitalid'),
            );
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MTerm = $this->LoadApiModelMedical('term');
            $dataArr = $MTerm->getTerms(null, $whArr, $exArr);
            // print_r($dataArr);
            if(is_array($dataArr)){
                $this->response($dataArr);
            }
        }   
        $this->error('没有找到指标');
    }
    /*
    * desc: 后台添加指标
    * call: curl -d "hospitalid=120&name=tttw&itype=20&vtype=1&val[]=1&val[]=2" http://api.medical.me/term/add?token=8bf3ce3551bfa311da971e001ca3a391
    *
    */
    function actionAdd()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost()) {
            $postArr = $this->posts('name,itype,vtype,editbypat');
            $postArr['userid'] = $userid;

            $values  = $this->posts('val');

            $MTerm = $this->LoadApiModelMedical('term');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            
            $postArr['hospitalid'] = $user['hospitalid'];
            
            if($role >= 20){
                $retArr = $MTerm->addTerm($postArr, $values);
            }else{
                $this->error('无权限操作');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $term = $retArr['term'];
                $this->response($term);
            }
        }
        $this->error('添加指标失败');
    }
 
    /*
    * desc: 删除指标
    * call: curl -d "id=4" http://api.medical.me/term/drop?token=8bf3ce3551bfa311da971e001ca3a391
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $termid=$this->post('id')){
            $MTerm = $this->LoadApiModelMedical('term');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 20){
                    $ok = $MTerm->dropTerm($termid);
                    if($ok){
                        $this->message('指标已删除');
                    }
                }
            }
        }
        $this->error('无权限操作');
    }
    /*
    * desc: 更新指标/值
    * call: curl "http://api.medical.me/term/detail?token=8bf3ce3551bfa311da971e001ca3a391&id=2" -d "name=blood2"
    *
    */
    function actionDetail()
    {
        //判断是否登入
        $userid = $this->userid;

        $termid = $this->get('id');
        $MTerm = $this->LoadApiModelMedical('term');
        if($this->isPost() && $termid){
            //更新profile
            $postArr = $this->posts('hospitalid,name,itype,vtype,editbypat');
            $values  = $this->posts('id,val');
            // $termid  = $this->post('termid');
            CFun::removeArrayNull($values);
            $retArr = $MTerm->updateTerm($termid, $postArr, $values);
            if(!$retArr['status']){
                $this->error('设置资料失败');
            }
        }
        $term = $MTerm->getTerm($termid, null, array('join_val'=>true,'join_cate'=>true));
        // print_r($term);
        if($term){
            $this->response($term);
        }
        $this->error('获取资料失败');
    }
};

<?php
/**
 * 项目的模板相关
 *
 *
 *
 *
*/
class KTpl extends CControllerApi{

    /*
    * desc: 获取项目的模板列表
    * call: curl "http://api.medical.me/project/tpl/list?token=b5ad52a3b0129e1ff7b51a6f9e627777&projectid=20"
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
        );

        if($projectid = $this->get('projectid')) {
            $whArr = array(
                'projectid'  => $projectid,
                'hospitalid' => $this->get('hospitalid'),
                'username%'  => $this->get('username'),
            );
            CFun::removeArrayNull($whArr);
            // print_r($whArr);

            $MProject = $this->LoadApiModelMedical('project');
            $patientArr = $MProject->getAppends('tpl',$whArr,$exArr);
            // print_r($patientArr);
            if($patientArr){
                $this->response($patientArr);
            }
        }
        $this->error('没有找到任何新模板信息');
    }
    /*
    * desc: 为项目添加模板
    * call: curl -d "projectid=24&tplid[]=9" http://api.medical.me/project/tpl/append?token=b5ad52a3b0129e1ff7b51a6f9e627777
    *
    */
    function actionAppend()
    {
        $userid = $this->userid;//模板的userid

        if($this->isPost() && $projectid=$this->post('projectid')) {
            $postArr = $this->posts('tplid');     //模板id序列

            $MProject  = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);

            if($role >= 20){
                $ok = $MProject->appendTpl($projectid, $postArr, $this->post('overwrite'));
            }else{
                $this->error('操作无权限');
            }
            if($ok){
                $this->message('添加成功');
            }
        }
        $this->error('添加项目失败');
    }
 
    /*
    * desc: 删除一个模板
    * call: curl -d "id=4" http://api.medical.me/project/tpl/drop?token=386b710e50c91f9b056a1218a30078fe
    *
    */
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $id=$this->post('id')){
            $MProject = $this->LoadApiModelMedical('project');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 20){
                    $ok = $MProject->dropAppended($id,'tpl');
                    if($ok){
                        $this->message('模板已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
};

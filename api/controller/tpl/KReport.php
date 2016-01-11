<?php
/**
 * 模板报告报告相关
 *
 *
 *
 *
*/
class KReport extends CControllerApi{

    /*
    * desc: 获取一模板报告列表
    * call: curl "http://api.medical.me/tpl/report/list?token=ca2b916cb63d382812da410c8b9fed7f&tplid=2"
    *
    */
    function actionList()
    {
        $userid = $this->userid;//管理员的userid
        $page   = $this->get('page', 1);
        $limit  = $this->get('limit', 10);
        $exArr  = array(
            'page'  => $page,
            'limit' => $limit,
        );

        if($tplid = $this->get('tplid')) {
            $whArr = array(
                'userid'=>$userid,
                'patientid' => $this->get('patientid'),
            );
            CFun::removeArrayNull($whArr);
            $MTpl = $this->LoadApiModelMedical('tpl');
            $reportArr = $MTpl->getTplReports($tplid, $whArr, $exArr);
            // $this->dump($reportArr);
            if(false !== $reportArr){
                $this->response($reportArr);
            }
        }
        $this->error('尚未填写报告');
    }
    /*
    * desc: 获取一模板报告的详情
    * call: curl "http://api.medical.me/tpl/report/detail?token=ca2b916cb63d382812da410c8b9fed7f&id=1"
    *
    */
    function actionDetail()
    {
        $userid = $this->userid;//管理员的userid

        if(1) {
            $reportid = $this->get('id');
            $tplid    = $this->get('tplid');
            $periodid = $this->get('periodid');
            $MTpl = $this->LoadApiModelMedical('tpl');
            $report = $MTpl->getTplReport($reportid, array('tplid'=>$tplid, 'periodid'=>$periodid));
            // $this->dump($report);
            if($report){
                $tpl = $MTpl->getTpl($report['tplid'],null,array('join_detail'=>true,'join_term'=>true,'join_cate'=>true,'join_sharp'=>true));
                // $this->dump($tpl);
                $this->response(array('report'=>$report,'tpl'=>$tpl));
            }else{
                $this->error('报告不存在', 404);
            }
        }
        $this->error('报告不存在');
    }
    /*
    * desc: 保存一分报告
    * call: curl -d "tplid=10&periodid=0&patientid=93&detailid[]=21&detailid[]=22&answer[]=1&answer[]=2" http://api.medical.me/tpl/report/commit?token=550a48ceb7dab41f53af1182d103053b
    * call: curl -d "tplid=27&periodid=0&patientid=163&detailid[]=235&detailid[]=229&answer[]=7,8,9&answer[]=2" http://115.29.176.160/tpl/report/commit?token=2a122f939c99c3dd87b480560555229f
    *
    */
    function actionCommit()
    {
        $userid = $this->userid;//管理员的userid

        if($tplid = $this->post('tplid')) {
            $reportid = $this->post('reportid');
            $postArr  = $this->posts('tplid,patientid,remark');
            $details  = $this->posts('id,detailid,answer');
            $periodid = $this->post('periodid');

            $MTpl  = $this->LoadApiModelMedical('tpl');
            $MUser = $this->LoadApiModelMedical('user');
            $user  = $MUser->getUser($userid);
            $role  = intval($user['role']);
            if(-10 == $role){
                $postArr['patientid'] = $userid;
            }

            $postArr['hospitalid'] = $user['hospitalid'];//创建者的医院
            $postArr['userid']     = $userid;
            if(90 == $role){
                $this->error('操作无权限:也许是超级管理员');
                $postArr['hospitalid'] = $this->post('hospitalid');
            }
            
            if(1 || $role <= 20){
                $retArr = $MTpl->saveTplReport($reportid, $periodid, $postArr, $details);
                // print_r($retArr);
            }else{
                $this->error('操作无权限');
            }
            // print_r($retArr);
            if(1 == intval($retArr['status'])){
                $report = $retArr['report'];
                $this->response($report);
            }else{
                $this->error($retArr['message']);
            }
        }
        $this->error('添加模板报告失败');
    }
 
    //删除报告
    function actionDrop()
    {
        $userid = $this->userid;//管理员的userid

        if($this->isPost() && $tplid=$this->post('tplid')){
            $MTpl = $this->LoadApiModelMedical('tpl');
            $MUser = $this->LoadApiModelMedical('user');
            $user = $MUser->getUser($userid);
            if($user){
                $role = intval($user['role']);
                if($role >= 80){
                    $ok = $MTpl->dropTpl($tplid);
                    if($ok){
                        $this->response(1, '模板报告已删除');
                    }
                }
            }
        }
        $this->error('操作无权限');
    }
};

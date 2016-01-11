<?php
/**
 * 模板的周期
 *
 *
 *
 *
*/
class KPeriod extends CControllerApi{
 
    /*
    * desc: 设置分类
    * call: curl http://api.medical.me/tpl/period/set?token=fa3a7de6fc2113ce0161f26cf57548fa  -d "periodname[]=p1&periodname[]=p2&tplid=10"
    *
    */
    function actionSet()
    {
        $userid = $this->userid;

        $MTpl = $this->LoadApiModelMedical('tpl');
        if($tplid  = $this->post('tplid')){
            //更新profile
            $postArr = $this->posts('id,periodname');
            // $MUser   = $this->LoadApiModelMedical('user');
            $ok  = $MTpl->setPeriods($tplid, $postArr, $this->post('overwrite',true));
            if($ok){
                $this->message('设置周期成功');
            }
        }
        $this->error('设置周期失败或参数不完整', 500);
        // print_r($info);
    }
};

<?php
/**
 * desc: 模板及模板报告
 *
 *
 *
*/

class MTpl extends CHookModel {

    private $tTpl     = 'tpl';                  // 模板
    private $tTdetail = 'tpl_detail';           // 模板详情
    private $tReport  = 'tpl_report';           // 模板调研
    private $tRdetail = 'tpl_report_detail';    // 模板调研详细记录
    private $tPeriod  = 'tpl_period';           // 周期
       
    /*
    * desc: 获取多条tpl记录(加了个s是为了区分tpl)
    *
    *
    */
    public function getTpls($tplids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $tplids){
            if(is_array($tplids)){
                $whArr = array_merge(array('id in'=>$tplids), $whArr); 
            }else{
                $whArr = array_merge(array('id'=>$tplids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated  = isset($exArr['aggregated'])?$exArr['aggregated']:false;      //标明是否为聚合查询
        $join_detail = isset($exArr['join_detail'])?$exArr['join_detail']:false;    //join参考值
        $join_report = isset($exArr['join_report'])?$exArr['join_report']:false;
        $join_term   = isset($exArr['join_term'])?$exArr['join_term']:false;
        $join_sharp  = isset($exArr['join_sharp'])?$exArr['join_sharp']:false;
        $join_user   = isset($exArr['join_user'])?$exArr['join_user']:false;    //是否要查询用户(user)信息
        $join_period = isset($exArr['join_period'])?$exArr['join_period']:true;
        // $swap_key    已更名为keyfield//标明是否为聚合查询
        if(!$aggregated){
            if($join_detail){
                $exArr['join']['tpl_detail'] = "id:tplid";
            }
            if($join_detail){
                $exArr['join']['tpl_period periods'] = "id:tplid";
            }
        }

        /***************************join end********************/

        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tTpl, $whArr, $exArr);
        if(!$dataArr)return false;
        //业务处理...
        if(!$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_report){
                
            }
            if($join_term && $join_detail){ //查询指标
                $MTerm = $this->LoadApiModelMedical('term');
                foreach($rowArr as &$r0006){
                    $tplDetails = &$r0006['tpl_detail'];
                    $termid_arr = $this->getArrayColumn($tplDetails,'termid');
                    
                    $termArr = $MTerm->getTerms($termid_arr, null, array('limit'=>count($termid_arr),'only_data'=>true,'keyas'=>'id','join_val'=>true, 'fields'=>'^ctime,utime'));
                    $tplDetails = $this->joinToArray($tplDetails, $termArr,'termid:id','term');
                }
            }
            if($join_sharp && $join_detail){
                $MSharp = $this->LoadApiModel('tree')->Type('sharp');
                foreach($rowArr as &$r0007){
                    $tplDetails = &$r0007['tpl_detail'];
                    $sharpid_arr = $this->getArrayColumn($tplDetails,'sharpid');
                    $sharpid2_arr = $this->getArrayColumn($tplDetails,'sharpid2');
                    $sharpid_arr = array_unique(array_merge($sharpid_arr, $sharpid2_arr));
                    $sharpArr    = $MSharp->getNodes($sharpid_arr, null, array('limit'=>count($sharpid_arr),'only_data'=>true,'keyas'=>'id','join_val'=>true, 'fields'=>'^tplid'));
                    $r0007['sharp'] = $sharpArr;
                    // $this->dump($tplDetails);
                }
                
            }
        }
        return $dataArr;
    }
    /*
    * desc: 获取一条tpl记录
    *
    *
    */
    public function getTpl($tplid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $tplid){
            $whArr = array_merge(array('id'=>$tplid), $whArr);  //保证id在前
        }
        $exArr['limit']       = 1;
        $exArr['only_data']   = true;
        $exArr['join_report'] = isset($exArr['join_report'])?$exArr['join_report']:true;
        $rowArr = $this->getTpls(null, $whArr, $exArr);
        //$this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }
    /*
    * desc: 添加一模板
    *
    *@postArr  --- arr 模板基本信息
    *@details  --- arr 模板详情(实为指标信息)
    *              允许为空,为空表示无参考值
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               tpl    --- tpl信息
    *               ) 
    */
    public function addTpl($postArr, $details=array())
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'tpl'=>null);
        $postArr = $this->removeArrayNull($postArr);
        foreach($details as $key=>$r0001){
            if(empty($r0001))unset($details[$key]);//值
        }
        
        //数据检查
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(!isset($postArr['hospitalid'])) {
            $retArr['message'] = '医院不能为空';
            return $retArr;
        }
        $old = $this->getTpl(null, array('hospitalid'=>$postArr['hospitalid'],'name'=>$postArr['name']));
        if($old){
            $retArr['message'] = '该模板已存在';
            $retArr['tpl'] = $old;
            return $retArr;
        }
        $termArr = CUtil::formArrayFormatting($details);
        $sorter  = 127;
        foreach($termArr as $k=>&$r0004){
            if(empty($r0004['termid'])){
                unset($termArr[$k]);continue;
            }
            $r0004['sorter'] = $sorter--;
        }
        $postArr['ctime'] = $postArr['utime'] = date("Y-m-d H:i:s");
        //end 数据 20150820097990

        // print_r($postArr);
        // print_r($termArr);
        // exit;
        
        $id = $this->addAtom($this->tTpl, $postArr);
        if($id){
            //添加参考值*********************
            $finallyok = true;
            if(!empty($termArr)){
                foreach($termArr as &$r0002){
                    $r0002['tplid']  = $id;
                    // $r0002['ctime']   = date("Y-m-d H:i:s");
                }
                $okd = $this->addMore($this->tTdetail, $termArr);
                if(!$okd){
                    $this->dropTpl($id);
                    $finallyok = false;
                }
            }
            //添加参考值******************end

            if($finallyok){
                $tpl = $this->getTpl($id);
                $retArr['status']  = 1;
                $retArr['tpl']    = $tpl;
                $retArr['message'] = '添加模板成功';
            }
        }
        return $retArr;
    }
    /*
    * desc: 更新一个模板
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               tpl    --- 模板信息
    *               )
    *
    */
    public function updateTpl($tplid, $postArr, $details=null)
    {
        $retArr = array('status'=>0, 'message'=>'数据不合法', 'tpl'=>null);

        $postArr = $this->removeArrayNull($postArr);
        if(!empty($details)){
            if(empty($details['termid'])) {
                $retArr['message'] = '模板数据不合法';
                return $retArr;
            }
        }

        //数据检查
        if(empty($postArr) || !$tplid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getTpl($tplid);
        if(!$old){
            $retArr['message'] = 'tpl不存在!';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");

        if(!empty($postArr['name'])){
            $oldt = $this->getTpl(null, array('hospitalid'=>$old['hospitalid'],'name'=>$postArr['name']));
            if($oldt){
                $retArr['message'] = '该模板名已存在';
                return $retArr;
            }
        }
        //end 数据检查
        if($details){
            $valueArr = CUtil::formArrayFormatting($details);
            foreach($valueArr as $k=>&$val){
                if(empty($val['termid'])){
                    unset($valueArr[$k]);continue;
                }
                $val = $this->removeArrayNull($val);
            }
        }
        // print_r($valueArr);exit;
        $ok = $this->updateData($this->tTpl,$postArr,$tplid);
        // var_dump(CUtil::IsFalse($ok));
        if(!CUtil::IsFalse($ok)){
            $tpl = $this->getTpl($tplid);
            //更新参考值=====================================
            if($details && !empty($valueArr)){
                $_wh_ = array('tplid'=>$tplid); //查询详情(tpl_detail)的条件
                $oldValArr = $this->getMore($this->tTdetail, $_wh_, array('only_data'=>true, 'keyas'=>'id', 'aggregated'=>true,'limit'=>1000));
                // print_r($oldValArr);exit;
                $this->deleteData($this->tTdetail, $_wh_, 1000);
                $sorter = 127;
                foreach($valueArr as $r0003){
                    $r0003['tplid']  = $tplid;
                    $r0003['sorter']  = $sorter;
                    // $r0003['ctime']   = date("Y-m-d H:i:s");
                    if(!isset($r0003['id']))$r0003['id']=null;
                    $_old_id = intval($r0003['id']);
                    if(0 == $_old_id) {
                        unset($r0003['id']); //重要
                    }else{
                        $r0003['id'] = $_old_id;
                    }
                    // print_r($r0003);
                    if(isset($oldValArr[$_old_id])){
                        $r0003 = array_merge($oldValArr[$_old_id], $r0003);
                    }
                    // print_r($r0003);
                    $this->addAtom($this->tTdetail, $r0003);
                    $sorter--;
                }
            }
            //更新参考值==================================end
            $retArr['tpl'] = $tpl;
            $retArr['status'] = 1;
            $retArr['message'] = '更新成功';

        }
        return $retArr;
    }
    public function setPeriods($tplid, $postArr, $overwrite=true)
    {
        $tpl = $this->getTpl($tplid);
        if(!$tpl)return false;
        if($overwrite){
            $oldlist = $this->getData($this->tPeriod, array('tplid'=>$tplid), array('keyas'=>'id'));
            $this->deleteData($this->tPeriod, array('tplid'=>$tplid));
        }

        $perArr = CUtil::formArrayFormatting($postArr);
        foreach($perArr as &$r0004){
            $r0004['tplid'] = $tplid;
            if(isset($r0004['id']) && isset($oldlist[$r0004['id']])){
                $r0004 = array_merge($oldlist[$r0004['id']], $r0004);
            }
        }
        // print_r($perArr);exit;
        $whfields = empty($postArr['id'])?'tplid,periodname':'id';
        return $this->replaceData($this->tPeriod, $perArr, $whfields);
    }
    /*
    * desc: 删除模板
    * 步骤: 1,反审核tpl
    *       2,删除val
    *       3,删除tpl
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               tpl    --- tpl信息
    *               ) 
    */
    public function dropTpl($tplid)
    {
        $retArr = array('status'=>0, 'message'=>'系统错误', 'tpl'=>null);
        //数据
        if(!$tplid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id');
        $old = $this->getAtom($this->tTpl, $tplid, $exArr);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        $ok = $this->deleteData($this->tTpl, $tplid);
        if($ok){
            $this->deleteData($this->tTdetail, array('tplid'=>$tplid), 10000);
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }
        return $retArr;
    }

    /*
    * desc: 获取多条采购参考值记录
    *
    *
    */
    public function getTplReports($tplid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $tplid){
            if(is_array($tplid)){
                $whArr = array_merge(array('tplid in'=>$tplid), $whArr); 
            }else{
                $whArr = array_merge(array('tplid'=>$tplid), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated    = isset($exArr['aggregated'])?$exArr['aggregated']:false;      //标明是否为聚合查询
        $join_detail   = isset($exArr['join_detail'])?$exArr['join_detail']:false;      //join单据
        
        if(!$aggregated){
            if($join_detail) {
                $exArr['join']['tpl_report_detail'] = "id:reportid";
            }
        }
        /***************************join end********************/

        /***************************only_data*******************/
        $exArr['only_data'] = isset($exArr['only_data'])?$exArr['only_data']:false;
        $exArr['limit'] = isset($exArr['limit'])?$exArr['limit']:100;
        /***************************only_data end***************/
        // $this->dump($exArr);
        $dataArr = $this->getMore($this->tReport, $whArr, $exArr);
        if(!$dataArr)return $dataArr;

        if($exArr['only_data']){
            $rowArr = &$dataArr;
        }else{
            $rowArr = &$dataArr['data'];
        }
        foreach($rowArr as &$r0007){
            if(isset($r0007['tpl_report_detail'])){
                $r0007['tpl_report_detail'] = $this->fieldAsKey($r0007['tpl_report_detail'], 'detailid');
            }
        }
        return $dataArr;
    }

    /*
    * desc: 获取单条采购参考值记录
    *
    *
    */
    public function getTplReport($reportid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(!$reportid && !$whArr){
            return false;
        }
        if($reportid){
            $whArr = array_merge(array('id'=>$reportid), $whArr);
        }
        /*$whArr = array(
            'id' => $reportid,
            'tplid' => $tplid,
            'periodid' => $periodid,
        );*/
        $whArr = $this->removeArrayNull($whArr);

        $exArr = is_array($exArr)?$exArr:array();
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $exArr['join_detail'] = isset($exArr['join_detail'])?$exArr['join_detail']:true;

        $rowArr = $this->getTplReports(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }

    /*
    * desc: 保存tpl的一份报告(填写的结果)
    *
    *@reportid --- int 报告id(如果为空表示保存一份新的报告)
    *@postArr  --- arr 报告基本信息
    *@details  --- arr 报告详情(实为指标信息)
    *              允许为空,为空表示无参考值
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               report  --- 报告信息
    *               ) 
    */
    public function saveTplReport($reportid=null, $periodid=null, $postArr=array(), $details=array())
    {
        $logfile = 'MTpl';
        $retArr = array('status'=>0, 'message'=>'数据不合法', 'report'=>null);

        //数据检查
        $postArr = $this->removeArrayNull($postArr);
        if(!empty($details)){
            if(empty($details['detailid']) || empty($details['answer'])) {
                $retArr['message'] = '报告单数据不合法';
                return $retArr;
            }
        }
        if(empty($postArr) || empty($postArr['tplid']) || empty($postArr['hospitalid']) || empty($postArr['userid'])) {
            $retArr['message'] = '数据不完整';
            return $retArr;
        }
        if(empty($postArr['patientid'])) {
            $retArr['message'] = '无病人信息，不能继续';
            return $retArr;
        }
        $tpl = $this->getTpl($postArr['tplid'], null, array('join_detail'=>true));
        if(!$tpl){
            $retArr['message'] = 'tpl不存在';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");

        //end 数据检查

        //业务上的数据检查
        if(!$reportid){ //针对添加作数据检查
            /*if(!$periodid){
                $retArr['message'] = '周期不存在';
                return $retArr;
            }*/
            $periodinfo = $this->getAtom($this->tPeriod, $periodid);
            if(!$periodinfo){
                /*$retArr['message'] = '周期不存在';
                return $retArr;*/
                $periodinfo = array(
                    'periodname' => '',
                    'tplid' => $postArr['tplid'],
                    'id'=>0,
                );
            }
            $postArr['title'] = $periodinfo['periodname'];
            $postArr['periodid'] = $periodid;

            $period = intval($tpl['period']); //周期长度
            $freqs  = 1;//intval($tpl['freqs']);  //频次 一律为1
            if($periodinfo || 0 == $period){//一次性
                //修改于公元2015年12月21日
                //必经之路
                // $_wh_old_rpt = array('userid'=>$postArr['userid'],'tplid'=>$postArr['tplid']);
                $_wh_old_rpt = array('tplid'=>$postArr['tplid'],'periodid'=>$periodid);
            }else{
                //计算当前时间处于第几个周期,并得到这个周期的开始日期
                $t_start  = strtotime(date("Y-m-d",strtotime($tpl['ctime'])));
                $s_period = intval((time() - $t_start)/(86400*$period));
                $last_p_s = date("Y-m-d 00:00:00", $t_start + 86400*$period);//最近一个周期的起始时间
                $_wh_old_rpt = array('userid'=>$postArr['userid'],'tplid'=>$postArr['tplid'],'utime>'=>$last_p_s);
            }
            // $old = $this->getTplReport(null,$_wh_old_rpt,array('aggregated'=>true,'order'=>'id desc'));
            
            $old = $this->getTplReport(null, $_wh_old_rpt, array('aggregated'=>true,'order'=>'id desc'));
            if($old){//如果已经存在一分报告
                //获取已填写的次数
                $cnteds = $this->getCount($this->tReport,$_wh_old_rpt);
                $utime  = $old['utime'];          //上次保存时间
                if(1 || 0==$period || $cnteds>=$freqs){
                    // if($freqs > 1) return;  //如果是多次就返回,因为不知道更新哪条好(也可以更新最后一条)
                    $reportid = $old['id']; //最近一周期已经填写过了就不能再添加了,赋值reportid是为了接下来作更新操作
                }
                /*
                switch($period){
                    case 0:
                        $reportid = $old['id'];
                        break; //一次性的就不能再添加了
                    case -1:
                        if(CTime::IsToday($utime) && $cnteds>=$freqs){//是今天
                            $reportid = $old['id'];
                        }
                        break;
                    case -7:
                        if(CTime::IsWeek($utime)){//是本周
                            $reportid = $old['id'];
                        }
                        break;
                    case -30:
                        if(CTime::IsMonth($utime)){//是本月
                            $reportid = $old['id'];
                        }
                        break;
                    case -365:
                        if(CTime::IsYear($utime)){//是当年
                            $reportid = $old['id'];
                        }
                        break;
                }*/
            }
        }
        //end 业务上的数据检查

        CLog::WriteLog(array('msg'=>'commit数据', 'reportid'=>$reportid,'periodid'=>$periodid,'postArr'=>$postArr,'details'=>$details), $logfile);

        if($reportid){
            $ok = $this->updateData($this->tReport, $postArr,$reportid);
        }else{
            $postArr['ctime'] = $postArr['utime'];
            $ok = $reportid = $this->addAtom($this->tReport, $postArr);
        }
        if(CUtil::NoFalse($ok)){
            $report = $this->getTplReport($reportid,null,array('aggregated'=>true));
            
            //更新报告值=====================================
            if($details){
                $valueArr = CUtil::formArrayFormatting($details,0);

                CLog::WriteLog(array('msg'=>'commit值', 'valueArr'=>$valueArr), $logfile);

                $tpl_did_arr = $this->getArrayColumn($valueArr, 'detailid');
                $detailArr = $this->getData($this->tTdetail, array('id in'=>$tpl_did_arr), array('limit'=>count($tpl_did_arr),'keyas'=>'id'));
                $termid_arr = $this->getArrayColumn($detailArr, 'termid');
                $MTerm = $this->LoadApiModelMedical('term');
                $termArr = $MTerm->getTerms($termid_arr, null, array('limit'=>count($termid_arr),'only_data'=>true,'keyas'=>'id','fields'=>'^ctime,utime'));
                // print_r($termArr);

                foreach($valueArr as $k=>&$r0005){
                    if(empty($r0005['detailid'])){
                        unset($valueArr[$k]);continue;
                    }
                    //检查detailid在数据库中是否存在,不存在则删除
                    if(!isset($detailArr[$r0005['detailid']])){
                        unset($valueArr[$k]);continue;
                    }
                    //检查指标是否在指标库中存在,存在则将termid及输入类型存放tpl_report_detail中
                    $detail = $detailArr[$r0005['detailid']];
                    if(isset($termArr[$detail['termid']])){
                        $term = $termArr[$detail['termid']];
                        $r0005['termid'] = $term['id'];
                        $r0005['itype']  = $term['itype'];
                        $r0005['vtype']  = $term['vtype'];
                    }
                    $r0005 = $this->removeArrayNull($r0005);
                }

                if(!empty($valueArr)){
                    $_wh_ = array('reportid'=>$reportid); //查询详情(tpl_detail)的条件
                    $oldValArr = $this->getMore($this->tRdetail, $_wh_, array('only_data'=>true, 'keyas'=>'id', 'aggregated'=>true,'limit'=>1000));
                    // $this->deleteData($this->tRdetail, $_wh_, 1000);
                    $sorter = 127;
                    foreach($valueArr as $r0003){
                        $r0003['tplid']    = $postArr['tplid'];
                        $r0003['reportid'] = $reportid;
                        $r0003['sorter']   = $sorter;
                        $r0003['utime']    = date("Y-m-d H:i:s");

                        $_old_id = intval($r0003['id']);
                        unset($r0003['id']);
                        if(0 == $_old_id) { //表示是新增的
                            $old_rdetail = $this->getAtom($this->tRdetail,array('reportid'=>$reportid,'detailid'=>$r0003['detailid']));
                            if($old_rdetail){ //如果是同一报告中的同一指标已存在,那只能修改
                                // print_r($r0003);itype
                                // echo 'eeeeeeeeeeeeeeeee';
                                $this->updateData($this->tRdetail, $r0003, $old_rdetail['id']);
                            }else{
                                $this->addAtom($this->tRdetail, $r0003);
                            }
                        }else{
                            $r0003['id'] = $_old_id;
                            if(isset($oldValArr[$_old_id])){
                                $r0003 = array_merge($oldValArr[$_old_id], $r0003);//原id存在
                            }
                            $this->updateData($this->tRdetail, $r0003, $_old_id);
                        }
                        $sorter--;
                    }

                    //填充未填写的值
                    if(isset($tpl['tpl_detail'])){
                        $tplValList = $tpl['tpl_detail'];
                        foreach($tplValList as $tplVal){
                            $_detailid = $tplVal['id'];
                            $_old = $this->getAtom($this->tRdetail,array('reportid'=>$reportid,'detailid'=>$_detailid));
                            if(!$_old){
                                $blankArr = array(
                                    'reportid'=>$reportid,
                                    'detailid'=>$_detailid,
                                    'tplid'   => $tplVal['tplid'],
                                    'termid'  => $tplVal['termid'],
                                    'utime'   => date('Y-m-d H:i:s'),
                                );
                                $this->addAtom($this->tRdetail, $blankArr);
                            }
                        }
                    }
                    //end 填充未填写的值
                }
            }
            //更新报告值==================================end
            $retArr['report']  = $report;
            $retArr['status']  = 1;
            $retArr['message'] = '更新成功';
        }
        return $retArr;
    }
};


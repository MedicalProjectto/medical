<?php
/**
 * desc: 检查指标及指标值
 *
 *
 *
*/

class MTerm extends CHookModel {

    private $tTerm  = 'term';               // 指标
    private $tVal   = 'term_val';           // 指标参考值
       
    /*
    * desc: 获取多条term记录(加了个s是为了区分term)
    *
    *
    */
    public function getTerms($termids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $termids){
            if(is_array($termids)){
                $whArr = array_merge(array('id in'=>$termids), $whArr); 
            }else{
                $whArr = array_merge(array('id'=>$termids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated  = isset($exArr['aggregated'])?$exArr['aggregated']:false;      //标明是否为聚合查询
        $join_val    = isset($exArr['join_val'])?$exArr['join_val']:false;    //join参考值
        $join_result = isset($exArr['join_result'])?$exArr['join_result']:false;
        $join_rval   = isset($exArr['join_rval'])?$exArr['join_rval']:false;
        $join_user   = isset($exArr['join_user'])?$exArr['join_user']:false;    //是否要查询用户(user)信息
        // $swap_key    已更名为keyfield//标明是否为聚合查询
        $join_cate   = isset($exArr['join_cate'])?$exArr['join_cate']:false;
        if(!$aggregated){
            if($join_val){
                $exArr['join']['term_val'] = "id:termid|order=sorter";
            }
            /*if($join_result){
                $exArr['join']['term_result'] = "id:termid";
            }
            if($join_rval){
                $exArr['join']['term_result_val'] = "id:termid";
            }*/
        }

        /***************************join end********************/

        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        $exArr['order'] = isset($exArr['order'])?$exArr['order']:'id';
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tTerm, $whArr, $exArr);
        if(!$dataArr)return false;
        //业务处理...
        if(!$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_cate){
                $MCate   = $this->LoadApiModel('tree')->Type('cate');
                $cid_arr = $this->getArrayColumn($rowArr, 'cateid');
                $cateArr = $MCate->getNodes($cid_arr, null, array('limit'=>count($cid_arr),'only_data'=>true,'fields'=>'id,name'));
                $rowArr  = $this->joinToArray($rowArr, $cateArr,'cateid:id','cate');
            }
        }
        return $dataArr;
    }
    /*
    * desc: 获取一条term记录
    *
    *
    */
    public function getTerm($termid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $termid){
            $whArr = array_merge(array('id'=>$termid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getTerms(null, $whArr, $exArr);
        //$this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }
    /*
    * desc: 添加一指标
    *
    *@postArr  --- arr 单的基本信息
    *@valArr   --- arr 该单的参考值(一行一条记录)
    *              允许为空,为空表示无参考值
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               term    --- term信息
    *               ) 
    */
    public function addTerm($postArr, $valArr=array())
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'term'=>null);
        $postArr = $this->removeArrayNull($postArr);
        foreach($valArr as $key=>$r0001){
            if(empty($r0001))unset($valArr[$key]);//检查值
        }
        // print_r($postArr);
        // print_r($valArr);

        //数据检查
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(!isset($postArr['hospitalid'])) { //可以为0
            $retArr['message'] = '医院不能为空';
            return $retArr;
        }
        if(empty($postArr['itype']) || empty($postArr['vtype'])) {
            $retArr['message'] = '指标输入类型和值类型不能为空';
            return $retArr;
        }
        if(intval($postArr['itype'])>=20 && empty($valArr)) {
            $retArr['message'] = '选项值不能为空';
            return $retArr;
        }

        $valueArr = CUtil::formArrayFormatting($valArr);
        $sorter = count($valueArr);
        foreach($valueArr as $k=>&$r0004){
            if(empty($r0004['val'])){
                unset($valueArr[$k]);continue;
            }
            $r0004['sorter'] = $sorter--;
        }
        $postArr['ctime'] = $postArr['utime'] = date("Y-m-d H:i:s");
        //end 数据检查 20150820097990
        
        $id = $this->addAtom($this->tTerm, $postArr);
        if($id){
            //添加参考值*********************
            $finallyok = true;
            if(!empty($valueArr)){
                foreach($valueArr as &$r0002){
                    $r0002['termid']  = $id;
                    // $r0002['ctime']   = date("Y-m-d H:i:s");
                }
                $okd = $this->addMore($this->tVal, $valueArr);
                if(!$okd){
                    $this->dropTerm($id);
                    $finallyok = false;
                }
            }
            //添加参考值******************end

            if($finallyok){
                $term = $this->getTerm($id);
                $retArr['status']  = 1;
                $retArr['term']    = $term;
                $retArr['message'] = '添加指标成功';
            }
        }
        return $retArr;
    }
    /*
    * desc: 更新一个指标
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               term    --- 指标信息
    *               ) 
    *
    */
    public function updateTerm($termid, $postArr, $valArr=null)
    {
        $retArr = array('status'=>0, 'message'=>'数据不合法', 'term'=>null);

        $postArr = $this->removeArrayNull($postArr);

        if(!empty($valArr)){
            if(empty($valArr['val'])) {
                $retArr['message'] = '指标数据不合法';
                return $retArr;
            }
        }

        //数据检查
        if(empty($postArr) || !$termid) {
            $retArr['message'] = '数据不合法';
            // return $retArr;
        }
        $old = $this->getTerm($termid);
        // print_r($old);
        if(!$old){
            $retArr['message'] = 'term不存在!';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");
        //end 数据检查
        if($valArr){
            $valueArr = CUtil::formArrayFormatting($valArr);
            foreach($valueArr as $k=>&$val){
                if(empty($val['val'])){
                    unset($valueArr[$k]);continue;
                }
                $val = $this->removeArrayNull($val);
            }
        }
        
        $ok = $this->updateData($this->tTerm,$postArr,$termid);
        // var_dump(CUtil::IsFalse($ok));
        if(!CUtil::IsFalse($ok)){
            $term = $this->getTerm($termid);
            //更新参考值=====================================
            if($valArr && !empty($valueArr)){
                $_wh_ = array('termid'=>$termid);
                $oldValArr = $this->getVals(null, $_wh_, array('only_data'=>true, 'keyas'=>'id', 'aggregated'=>true,'limit'=>1000));
                $this->deleteData($this->tVal, $_wh_, 1000);
                $sorter = 127;
                foreach($valueArr as $r0003){
                    $r0003['termid']  = $termid;
                    $r0003['sorter']  = $sorter;
                    // $r0003['ctime']   = date("Y-m-d H:i:s");
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
                    $this->addAtom($this->tVal, $r0003);
                    $sorter--;
                }
            }
            //更新参考值==================================end
            $retArr['term'] = $term;
            $retArr['status'] = 1;
            $retArr['message'] = '更新成功';

        }
        return $retArr;
    }

    /*
    * desc: 删除指标
    * 步骤: 1,反审核term
    *       2,删除val
    *       3,删除term
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               term    --- term信息
    *               ) 
    */
    public function dropTerm($termid)
    {
        $retArr = array('status'=>0, 'message'=>'系统错误', 'term'=>null);
        //数据检查
        if(!$termid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id');
        $old = $this->getAtom($this->tTerm, $termid, $exArr);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        $ok = $this->deleteData($this->tTerm, $termid);
        if($ok){
            $this->deleteData($this->tVal, array('termid'=>$termid), 10000);
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
    public function getVals($termid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $termid){
            if(is_array($termid)){
                $whArr = array_merge(array('termid in'=>$termid), $whArr); 
            }else{
                $whArr = array_merge(array('termid'=>$termid), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated    = isset($exArr['aggregated'])?$exArr['aggregated']:false;      //标明是否为聚合查询
        $join_term     = isset($exArr['join_term'])?$exArr['join_term']:false;      //join单据
        
        if(!$aggregated){
            if($join_term) {
                $exArr['join']['term_term'] = "termid:id";
            }
        }
        /***************************join end********************/

        /***************************only_data*******************/
        $exArr['only_data'] = isset($exArr['only_data'])?$exArr['only_data']:false;
        $exArr['limit'] = isset($exArr['limit'])?$exArr['limit']:1000;
        /***************************only_data end***************/
        // $this->dump($exArr);
        return $this->getMore($this->tVal, $whArr, $exArr);

    }

    /*
    * desc: 获取单条采购参考值记录
    *
    *
    */
    public function getVal($valid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $valid){
            $whArr = array_merge(array('id'=>$valid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getVals(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }


    //整理更新时的post表单数据
    private function _trim_update_data(&$addArr)
    {
        $addArr['utime'] = date('Y-m-d H:i:s');
    }
    //整理添加时的post表单数据
    private function _trim_add_data(&$addArr)
    {
        $this->_trim_update_data($addArr);
        $addArr['ctime'] = $addArr['utime'] = date('Y-m-d H:i:s');
    }

};


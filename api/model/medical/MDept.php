<?php
/**
 * desc: 科室部门
 *
 *
 *
*/

class MDept extends CHookModel {

    private $tDept   = 'department';          // 科室部门
       
    /*
    * desc: 获取科室部门列表
    *
    *
    */
    public function getDepts($deptids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $deptids){
            if(is_array($deptids)){
                $whArr = array_merge(array('id in'=>$deptids), $whArr); 
            }else{
                $whArr = array_merge(array('id'=>$deptids), $whArr);  //保证id在前
            }
        }

        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tDept, $whArr, $exArr);
        if(!$dataArr)return false;

        return $dataArr;
    }
    /*
    * desc: 获取一条商品记录
    *
    *
    */
    public function getDept($deptid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $deptid){
            $whArr = array_merge(array('id'=>$deptid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getDepts(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }

    /*
    * desc: 添加一个分类
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               dept   --- 商品信息
    *               )
    */
    public function addDept($postArr)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'Dept'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['name'])) {
            $retArr['message'] = '分类名称不能为空';
            return $retArr;
        }

        //end 数据检查
        
        $tDept = $this->tDept; //'Dept';         Dept
        $whArr = array("id >= "=> $postArr['parentid'] * 100);
        $whArr = array_merge($whArr, array("+id < "=> $postArr['parentid'] * 100 + 99));
        $dept = $this->getDept(null, $whArr, array('order' => 'id desc'));
        if ($dept) {
            $postArr['id'] = $dept['id'] + 10;
            if ($postArr['parentid']) {
                $postArr['id'] = $dept['id'] + 1;
            } else if ($dept['id'] == 90) {
                $retArr['status']  = -1;
                $retArr['message'] = "已不能再添加顶级分类!";
                return $retArr;
            }
        } else {
            $postArr['id'] = 10;
            if ($postArr['parentid']) {
                $postArr['id'] = $postArr['parentid'] * 100 + 10;
            }
        }

        $dbDept = $this->LoadDbModel($this->tDept);
        $id = $this->addAtom($this->tDept, $postArr);
        if($id){		
            $dept = $this->getDept($id);
            $retArr['status']  = 1;
            $retArr['Dept']   = $dept;
            $retArr['message'] = "添加商品分类成功!";
        }else{
            $retArr['message'] = $this->error = $dbDept->getError();
        }
        return $retArr;
    }

    /*
    * desc: 更新一个货品
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               Dept   --- 货品信息
    *               ) 
    *
    */
    public function updateDept($deptid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'Dept'=>null);

        //数据检查
        if(empty($postArr) || !$deptid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getDept($deptid);
        if(!$old){
            $retArr['message'] = '分类不存在!';
            return $retArr;
        }
        if(isset($postArr['name']) && empty($postArr['name'])) {
            $retArr['message'] = '分类名称不能为空';
            return $retArr;
        }
        //end 数据检查
        
        if ($postArr['parentid'] && $old['parentid'] != $postArr['parentid']) {
            $this->dropDept($old['id']);
            return $this->addDept($postArr);
        }
        
        $ok = $this->updateData($this->tDept,$postArr,array('id'=>$deptid));
        if($ok !== false){
            $dept = $this->getDept($deptid);
            $retArr['Dept'] = $dept;
            $retArr['status'] = 1;
            $retArr['message'] = '更新成功';
        }else{
            $retArr['message'] = $this->error = $dbDept->getError();
        }
        return $retArr;
    }

    /*
    * desc: 删除货品
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               Dept   --- 商户信息
    *               ) 
    */
    public function dropDept($deptid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'Dept'=>null);
 
        //数据检查
        if(!$deptid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id');
        $old = $this->getAtom($this->tDept, $deptid, $exArr);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        $ok = $this->deleteData($this->tDept, $deptid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '数据库错误';
        }
        return $retArr;
    }
};


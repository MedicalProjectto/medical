<?php
/**
 * desc: 科室部门
 *
 *
 *
*/

class MTree extends CHookModel {

    private $tableArr = array(
        'dept'  => '_department', //部门
        'sharp' => '_sharp',      //模板中的指标形式上的分类
        'cate'  => '_category',   //病例分类
        'kind'  => '_kind',       //病例分类
    );

    private $table    = null;

    public function Type($type)
    {
        if(isset($this->tableArr[$type])){
            $this->table = $this->tableArr[$type];
        }
        return $this;
    }
       
    /*
    * desc: 获取科室部门列表
    *
    *
    */
    public function getNodes($nodeids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $nodeids){
            if(is_array($nodeids)){
                $whArr = array_merge(array('id in'=>$nodeids), $whArr); 
            }else{
                $whArr = array_merge(array('id'=>$nodeids), $whArr);  //保证id在前
            }
        }

        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->table, $whArr, $exArr);
        if(!$dataArr)return false;

        return $dataArr;
    }
    /*
    * desc: 获取一条记录
    *
    *
    */
    public function getNode($nodeid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $nodeid){
            $whArr = array_merge(array('id'=>$nodeid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getNodes(null, $whArr, $exArr);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }

    /*
    * desc: 添加一个
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               node   --- 信息
    *               )
    */
    public function addNode($postArr)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'node'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['name'])) {
            $retArr['message'] = '名称不能为空';
            return $retArr;
        }
        //end 数据检查
        $id = $this->addAtom($this->table, $postArr);
        if($id){		
            $node = $this->getNode($id);
            $retArr['status']  = 1;
            $retArr['node']    = $node;
            $retArr['message'] = "添加成功";
        }else{
            $retArr['message'] = '添加失败';
        }
        return $retArr;
    }

    /*
    * desc: 更新一个货品
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               Node   --- 货品信息
    *               ) 
    *
    */
    public function updateNode($nodeid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'node'=>null);

        //数据检查
        if(empty($postArr) || !$nodeid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getNode($nodeid);
        if(!$old){
            $retArr['message'] = '不存在!';
            return $retArr;
        }
        if(isset($postArr['name']) && empty($postArr['name'])) {
            $retArr['message'] = '名称不能为空';
            return $retArr;
        }
        //end 数据检查
        
        if ($postArr['parentid'] && $old['parentid'] != $postArr['parentid']) {
            $this->dropNode($old['id']);
            return $this->addNode($postArr);
        }
        
        $ok = $this->updateData($this->table,$postArr,array('id'=>$nodeid));
        if($ok !== false){
            $node = $this->getNode($nodeid);
            $retArr['node'] = $node;
            $retArr['status'] = 1;
            $retArr['message'] = '更新成功';
        }else{
            $retArr['message'] = $this->error = $dbTree->getError();
        }
        return $retArr;
    }

    /*
    * desc: 删除货品
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               Node   --- 商户信息
    *               ) 
    */
    public function dropNode($nodeid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'node'=>null);
 
        //数据检查
        if(!$nodeid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id');
        $old = $this->getAtom($this->table, $nodeid, $exArr);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        $ok = $this->deleteData($this->table, $nodeid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '数据库错误';
        }
        return $retArr;
    }
};


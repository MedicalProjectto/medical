<?php
/**
 * desc: db版的基础常量
 *
 *
 *
*/

class MBase extends CHookModel {

    // private $tAccount  = 'account';     //资金帐户

    /*
    * desc: 获取其中一种基础信息
    *
    *
    */
    public function getBases($type, $baseids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $baseids){
            if(is_array($baseids)){
                $whArr = array_merge(array('id in'=>$baseids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$baseids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        /***************************join end********************/
        /***************************only_data*******************/
        $exArr['only_data'] = isset($exArr['only_data'])?$exArr['only_data']:true;
        $exArr['limit']     = isset($exArr['limit'])?$exArr['limit']:200;
        /***************************only_data end***************/

        $type  = $type?$type:'account';
        $table = 'bs_'.$type;
        $dataArr = $this->getMore($table, $whArr, $exArr);
        if(!$dataArr)return false;
        //业务处理...
        return $dataArr;
    }
    /*
    * desc: 获取一条日志记录
    *
    *
    */
    public function getBase($type, $baseid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $baseid){
            $whArr = array_merge(array('id'=>$baseid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getBases(null, $whArr, $exArr, $type);
        // $this->dump($rowArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return false;
    }
    /*
    * desc: 添加一个日志
    *
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               base   --- 日志信息
    *               )
    */
    public function addBase($type, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'base'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $postArr['ctime'] = date("Y-m-d H:i:s");
        //数据检查==================================end
        $table = 'bs_'.$type;
        $id = $this->addAtom($table, $postArr, array('log'=>'base'));
        if($id){
            $baseInfo = $this->getBase($id);
            $retArr['status']   = 1;
            $retArr['base'] = $baseInfo;
            $retArr['message']  = "添加日志成功";
        }else{
            // $retArr['message'] = null;
        }
        return $retArr;
    }
    /*
    * desc: 更新一个日志
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               base --- 日志信息
    *               )
    *
    */
    public function updateBase($type, $baseid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'base'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr) || !$baseid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getBase($baseid,null,null,$type);
        if(!$old){
            $retArr['message'] = '日志不存在';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");
        //数据检查==================================end

        $table = 'bs_'.$type;
        $ok = $this->updateData($table, $postArr, $baseid);
        if($ok){
            $baseInfo = $this->getBase($baseid);
            $retArr['base'] = $baseInfo;
            $retArr['status']   = 1;
            $retArr['message']  = '更新成功';
        }else{
            $retArr['message']  = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
    /*
    * desc: 切底删除日志
    * 步骤: 1, 删除base,base_profile表中的数据
    *       2, 将该用户的role设置成10(普通用户)
    *
    */
    public function dropBase($type, $baseid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'base'=>null);
        //数据检查=====================================
        if(!$baseid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id,storeid');
        $old = $this->getBase($baseid, null, null, $type);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        //数据检查==================================end

        $table = 'bs_'.$type;
        $ok = $this->deleteData($table, $baseid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
};


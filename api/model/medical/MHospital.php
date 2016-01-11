<?php
/**
 * desc: 医院操作相关方法
 *
*/

class MHospital extends CHookModel {

    private $tHospital = 'hospital';
    private $tProfile  = 'hospital_profile';
    private $tCate     = 'hospital_cate';

    /*
    * desc: 获取多条商家记录
    *
    */
    public function getHospitals($hospitalids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $hospitalids){
            if(is_array($hospitalids)){
                $whArr = array_merge(array('id in'=>$hospitalids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$hospitalids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated   = isset($exArr['aggregated'])?$exArr['aggregated']:false;  //标明是否为聚合查询
        $join_profile = isset($exArr['join_profile'])?$exArr['join_profile']:false;  
        $join_user    = isset($exArr['join_user'])?$exArr['join_user']:false;  //用户
        if($join_profile){
            $exArr['join']['hospital_profile'] = "id:hospitalid";
        }
        /***************************join end********************/
        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tHospital, $whArr, $exArr);
        if(!$dataArr)return false;
        //业务处理...
        if(!$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_user){
                $_uid_arr = $this->getArrayColumn($rowArr,'adminid');
                $MUser = $this->LoadApiModelMedical('user');
                
                $userArr = $MUser->getUsers($_uid_arr,null,array('fields'=>'^password,plain','limit'=>count($_uid_arr),'only_data'=>true));
                $rowArr = $this->joinToArray($rowArr, $userArr, 'adminid:id', 'user');
                // $this->dump($rowArr);
            }
        }
        return $dataArr;
    }

    public function getHospital($hospitalid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $hospitalid){
            $whArr = array_merge(array('id'=>$hospitalid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getHospitals(null, $whArr, $exArr);
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

    /*
    * desc: 添加一个商户(商家)
    *
    *@stype  --- str 'unknow','supply','build'
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               hospital   --- 商户信息
    *               )
    *
    */
    public function addHospital($postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'hospital'=>null);
        // print_r($postArr);exit;
        //数据检查
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        /*if(empty($postArr['hospitalname'])) {
            $retArr['message'] = '商户名称不能为空';
            return $retArr;
        }*/
        if(empty($postArr['mobile'])) {
            $retArr['message'] = '手机不能为空';
            return $retArr;
        }
        $old = $this->getHospital(null,array('mobile'=>$postArr['mobile']),array('aggregated'=>true));
        if($old){
            $retArr['status']  = 1;
            $retArr['message'] = '该商户已存在';
            return $retArr;
        }
        //end 数据检查

        $this->_trim_add_data($postArr);  //整理数据
        $_flag = 'build'==$postArr['type']?1:9;
        do{
            $postArr['id'] = $_flag.CTool::uniqueId(6);
            // $id = $dbHospital->add($postArr);
            $id = $this->addAtom($this->tHospital, $postArr);
        }while(!$id && ($loop=(isset($loop)?++$loop:1))<10);
        

        if($id){
            $retArr['id'] = $id;
            $retArr['hospital']  = $this->getHospital($id);
            $retArr['status'] = 1;
            $retArr['message'] = '添加商户成功';
        }else{
            $retArr['message'] = '系统繁忙';
        }
        return $retArr;
    }

    /*
    * desc: 手机注册一个商户,包括注册帐号
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               hospital   --- 商户信息
    *               user    --- 用户信息
    *               )
    *
    */
    public function registerHospital($postArr)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙，请稍候再试', 'elapsed'=>0);
        $dftArr = array(
            'type'       => 10,
            //以下是关于用户的信息
            'mobile'     => '',
            'telphone'   => '',
            'username'   => '',
            'role'       => 80,
        );
        $postArr = array_merge($dftArr, $postArr);
        $postArr['role'] = 80;
        
        if(empty($postArr['idcard']) || empty($postArr['mobile'])){
            $retArr['message'] = '数据不完整';
            return $retArr;
        }
        $postArr['plain']    = substr($postArr['mobile'], -6);
        $postArr['username'] = isset($postArr['contact'])?$postArr['contact']:'';
        if(empty($postArr['provid']) && !empty($postArr['address'])){
            //address应该含有省市区名称
            //根据地址获取经纬度
            $urlll = sprintf('https://maps.googleapis.com/maps/api/js/GeocodeService.Search?4s%s&7sUS&9szh-CN&callback=_xdc_._r57ur6&token=126799', rawurlencode($postArr['address']));
            // $llinfo = CUrl::curlGet($urlll);
            // print_r($llinfo);

            //根据城市名称获取行政区域id
            if(!empty($postArr['province'])){
                CLocation::GetLocationId($postArr,'province,city,area','proid','cityid','areaid');
            }
        }
        // print_r($postArr);exit;
        $MUser  = $this->LoadApiModelMedical('user');
        $retUArr = $MUser->addUser($postArr);
        if(1 == intval($retUArr['status'])){
            $user = $retUArr['user'];
            $postArr['adminid'] = $user['id'];
            $retSArr   = $this->addHospital($postArr);
            // print_r($retSArr);exit;
            if (1 == intval($retSArr['status'])) {
                $hospital = $retSArr['hospital'];
                $ret = $MUser->updateUser($user['id'], array('hospitalid'=>$hospital['id']));
                // $ret = $this->createRelative($hospitalid, $hospital['id']);
                $retArr['message']  = '添加成功';
                $retArr['status'] = 1;
                $retArr['hospital'] = $hospital;
                $retArr['user']  = $user;
            } else {
                $MUser->dropUser($user['id']);
                $retArr['message'] = '创建失败';
            }
        }else{
            if(isset($retUArr['user'])){
                $retArr['user'] = $retUArr['user'];
                $retArr['hospital'] = $this->getHospital($retUArr['user']['hospitalid']);
            }
            $retArr['message'] = '创建管理员时失败:'.$retUArr['message'];
        }

        return $retArr;
    }

    /*
    * desc: 更新一个商户(商家)
    *
    *return: array( status  --- 状态(1:成功,0:失败)
    *               message --- 提示信息
    *               hospital   --- 商户信息
    *               )
    *
    */
    public function updateHospital($hospitalid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'hospital'=>null);

        //数据检查
        if(empty($postArr) || !$hospitalid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getHospital($hospitalid);
        if(!$old){
            $retArr['message'] = '商户不存在!';
            return $retArr;
        }

        /*if(isset($postArr['hospitalname']) && empty($postArr['hospitalname'])) {
            $retArr['message'] = '商户名称不能为空';
            return $retArr;
        }*/
        if(isset($postArr['contact']) && empty($postArr['contact'])) {
            $retArr['message'] = '联系人不能为空';
            return $retArr;
        }
        //end 数据检查

        $this->_trim_update_data($postArr);  //整理数据
        $ok = $this->updateData($this->tHospital, $postArr, $hospitalid);
        if($ok){
            $retArr['message'] = '修改成功';
            $retArr['status']  = 1;
        }else{
            $retArr['message'] = '修改失败';
        }
        return $retArr;
    }

    /*
    * desc: 切底删除商户(商家)
    *
    */
    public function dropHospital($hospitalid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'hospital'=>null);

        //数据检查
        if(!$hospitalid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getHospital($hospitalid);
        if(!$old){
            $retArr['message'] = '商户不存在!';
            return $retArr;
        }

        $ok = $this->deleteData($this->tHospital, $hospitalid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
            $retArr['hospital']   = $old;
        }else{
            $retArr['message'] = '数据库错误';
        }
        return $retArr;;
    }
};
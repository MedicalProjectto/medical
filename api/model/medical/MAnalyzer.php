<?php
/**
 * desc: 分析器(实为日志)
 *
 *
 *
*/

class MAnalyzer extends CHookModel {

    private $tAnalyzer = 'analyzer';          // analyzer

    /*
    * desc: 获取多条日志记录(加了个s是为了区分analyzer)
    *
    *
    */
    public function getAnalyzers($analyzerids=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        $exArr = is_array($exArr)?$exArr:array();
        if(null !== $analyzerids){
            if(is_array($analyzerids)){
                $whArr = array_merge(array('id in'=>$analyzerids), $whArr);
            }else{
                $whArr = array_merge(array('id'=>$analyzerids), $whArr);  //保证id在前
            }
        }

        /***************************join************************/
        $aggregated    = isset($exArr['aggregated'])?$exArr['aggregated']:false;  //标明是否为聚合查询
        $join_user     = isset($exArr['join_user'])?$exArr['join_user']:true;     //要查询用户信息
        $join_project  = isset($exArr['join_project'])?$exArr['join_project']:true;
        if(!$aggregated){
        }
        /***************************join end********************/
        /***************************only_data*******************/
        $only_data = isset($exArr['only_data'])?$exArr['only_data']:false;
        /***************************only_data end***************/

        $dataArr = $this->getMore($this->tAnalyzer, $whArr, $exArr);
        //业务处理...
        if($dataArr && !$aggregated){
            if($only_data){
                $rowArr = &$dataArr;
            }else{
                $rowArr = &$dataArr['data'];
            }
            if($join_user){
                $_uid_arr = $this->getArrayColumn($rowArr,'userid');//项目分部
                $MUser = $this->LoadApiModelBuilding('user');
                
                $userArr = $MUser->getUsers($_uid_arr,null,array('limit'=>count($_uid_arr),'only_data'=>true));
                $rowArr = $this->joinToArray($rowArr, $userArr, 'userid:id', 'user');
                // $this->dump($rowArr);
            }
            if($join_project){
                $_pid_arr = $this->getArrayColumn($rowArr, 'projectid');
                $MProject = $this->LoadApiModelBuilding('project');
                $projArr  = $MProject->getProjects($_pid_arr,null,array('limit'=>count($_pid_arr),'aggregated'=>true,'only_data'=>true,'fields'=>'id,shortname','keyas'=>'id'));
                // $this->dump($projArr);
                $rowArr = $this->joinToArray($rowArr, $projArr, 'projectid:id', 'project');
                $dataArr['projects'] = $projArr;
            }
        }

        return $dataArr;
    }
    /*
    * desc: 获取一条日志记录
    *
    *
    */
    public function getAnalyzer($analyzerid=null, $whArr=array(), $exArr=array())
    {
        $whArr = is_array($whArr)?$whArr:array();
        if(null !== $analyzerid){
            $whArr = array_merge(array('id'=>$analyzerid), $whArr);  //保证id在前
        }
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getAnalyzers(null, $whArr, $exArr);
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
    *               analyzer   --- 日志信息
    *               )
    */
    public function addAnalyzer($postArr)
    {
        $retArr = array('status'=>0, 'message'=>'服务器繁忙,请稍候再试', 'analyzer'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr)) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        if(empty($postArr['userid'])) {
            $retArr['message'] = '没有用户';
            return $retArr;
        }
        $postArr['ctime'] = date("Y-m-d H:i:s");
        $postArr['hits']  = isset($postArr['hits'])?$postArr['hits']:1;
        //数据检查==================================end
        if(isset($postArr['flag'])){
            $old = $this->getAnalyzer(null,array('flag'=>$postArr['flag']));
            if($old){
                $new_hits = intval($old['hits']) + intval($postArr['hits']);
                return $this->updateAnalyzer($old['id'], array('hits'=>$new_hits));
            }
        }
        $id = $this->addAtom($this->tAnalyzer, $postArr);
        if($id){
            $analyzer = $this->getAnalyzer($id);
            $retArr['status']   = 1;
            $retArr['analyzer'] = $analyzer;
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
    *               analyzer --- 日志信息
    *               )
    *
    */
    public function updateAnalyzer($analyzerid, $postArr)
    {
        $retArr = array('status'=>0, 'message'=>'', 'analyzer'=>null);
        $postArr = $this->removeArrayNull($postArr);
        //数据检查=====================================
        if(empty($postArr) || !$analyzerid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $old = $this->getAnalyzer($analyzerid);
        if(!$old){
            $retArr['message'] = '日志不存在';
            return $retArr;
        }
        $postArr['utime'] = date("Y-m-d H:i:s");
        //数据检查==================================end

        $ok = $this->updateData($this->tAnalyzer, $postArr, $analyzerid);
        if($ok){
            $analyzerInfo = $this->getAnalyzer($analyzerid);
            $retArr['analyzer'] = $analyzerInfo;
            $retArr['status']   = 1;
            $retArr['message']  = '更新成功';
        }else{
            $retArr['message']  = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }
    /*
    * desc: 切底删除日志
    * 步骤: 1, 删除analyzer,analyzer_profile表中的数据
    *       2, 将该用户的role设置成10(普通用户)
    *
    */
    public function dropAnalyzer($analyzerid)
    {
        $retArr = array('status'=>0, 'message'=>'未知错误', 'analyzer'=>null);
        //数据检查=====================================
        if(!$analyzerid) {
            $retArr['message'] = '数据不合法';
            return $retArr;
        }
        $exArr = array('fields' => 'id,storeid');
        $old = $this->getAnalyzer($analyzerid);
        if(empty($old)){
            $retArr['message'] = '数据不存在';
            return $retArr;
        }
        //数据检查==================================end
        $ok = $this->deleteData($this->tAnalyzer, $analyzerid);
        if($ok){
            $retArr['status']  = 1;
            $retArr['message'] = '删除成功';
        }else{
            $retArr['message'] = '系统繁忙,请稍后再试';
        }
        return $retArr;
    }

    /**
     * 获取日志
     * @param unknown $where
     * @param unknown $gets
     * @return multitype:number string NULL
     */
    public function getStoreLog($where,$gets){
        $retArr = array('status'=>0, 'message'=>'未知错误', 'analyzer'=>null);
        if(empty($where)){
            $retArr['message'] = '参数错误';
            return $retArr;
        }
        $dbLog = $this->LoadDbModel($this->tAnalyzer, $this->dbname);
        return  $dbLog->wh($where)->gets($gets);
    }

    /**
     * 商户-数据统计
     * @param 商户ID $storeid
     * @return 统计数据
     */
    public function StoreGroupData($Whr=array()){
        if($Whr['startdate']){
            $where['ctime>=']=$Whr['startdate'];
        }
        if(isset($Whr['enddate'])&&$Whr['enddate']){
            $where['ctime<']= date('Y-m-d H:i:s',strtotime($Whr['enddate'] . "+1 day"));
        }
        $MStore =  $this->LoadApiModel('store');

        //获取group组下的storeid
        $storelist = $MStore->getStores(null,array('groupid'=>$Whr['groupid'],'enabled'=>1),array('only_data'=>1,'limit'=>9999999));
        $storeIds = array_column($storelist,'id');
        $where['storeid in'] = $storeIds;
        $where['type'] = 10;
        $where['eventid'] = 0;
        //取得所有数据：
        $arr = $this->getAnalyzers(null,$where,array('only_data'=>1,'limit'=>9999999));
        //访问人次：
        $outdata['browerCount'] =  $arr?array_sum(array_column($arr,'hits')):0;
        //访问人数
        $outdata['joinCount'] = $arr?count(array_unique(array_column($arr,'userid'))):0;
        //扫码人次：
        function callQrcode($item){
            return $item['origin']==20;
        }

        function callExchange2($item){
            return $item['status']==40;
        }
        if($arr)$arr = array_filter($arr,'callQrcode');
        $outdata['qrCodeCount'] =   $arr?array_sum(array_column($arr,'hits')):0;//扫码人次
        $outdata['jqrcount'] = $arr?count(array_unique(array_column($arr,'userid'))):0;//扫码人数
        //转发人数
        $where['type'] = 20;//转发操作
        //取得分享所有数据：
        $arr = $this->getAnalyzers(null,$where,array('only_data'=>1,'limit'=>9999999));
        //分享次数
        $outdata['sharecount'] = $arr?array_sum(array_column($arr,'hits')):0;
        //获取该商户的所有中奖名单
        $MExchange =  $this->LoadApiModel('exchange');
        $where['status !='] = 0;
        unset($where['type']);
        unset($where['eventid']);
        $arr = $MExchange->GetStoreExchange(null,$where,array('limit'=>9999999,'only_data'=>1));
        $outdata['winCount'] = $arr?count($arr):0;//总中奖人数
        if($arr)$arr = array_filter($arr,"callExchange");
        $outdata['getCount'] = $arr?count($arr):0;//已兑奖人数
        
        //购买数
        $MOrder = $this->LoadApiModel('order');
        unset($where['status !=']);
        $where['status in']=array(20,40);
        $orderArr = $MOrder->getOrders(null,null,$where,array('limit'=>9999999,'only_data'=>1));
        if($orderArr){
            $outdata['orderCount'] = count($orderArr);
            $outdata['winCount'] +=$outdata['orderCount'];
            $arr = array_filter($orderArr,"callExchange2");
            $outdata['getCount'] +=count($arr);
        }else{
            $outdata['orderCount']=0;
        }
        return $outdata;
    }

    /**
     * 商户-数据统计
     * @param 商户ID $storeid
     * @return 统计数据
     */
    public function StoreData($Whr=array()){
        $where['storeid'] = $Whr['storeid'];
        if($Whr['startdate']){
            $where['ctime>=']=$Whr['startdate'];
        }
        if(isset($Whr['enddate'])&&$Whr['enddate']){
            $where['ctime<']= date('Y-m-d H:i:s',strtotime($Whr['enddate'] . "+1 day"));
        }
        if(isset($Whr['flag'])&&$Whr['flag']){//权限获取各自
            $where['memberid'] = $Whr['memberid'];
        }
        //取得所有数据：
        $where['type'] = 10;
        $where['eventid'] = 0;
        //取得所有数据：
        $arr = $this->getAnalyzers(null,$where,array('only_data'=>1,'limit'=>9999999));
        //访问人次：
        $outdata['browerCount'] =  $arr?array_sum(array_column($arr,'hits')):0;
        //访问人数
        $outdata['joinCount'] = $arr?count(array_unique(array_column($arr,'userid'))):0;
        //扫码人次：
        function callQrcode($item){
            return $item['origin']==20;
        }

        function callExchange($item){
            return $item['status']==2;
        }
        function callExchange2($item){
            return $item['status']==40;
        }
        if($arr)$arr = array_filter($arr,'callQrcode');
        $outdata['qrCodeCount'] =   $arr?array_sum(array_column($arr,'hits')):0;//扫码人次
        $outdata['jqrcount'] = $arr?count(array_unique(array_column($arr,'userid'))):0;//扫码人数
        //转发人数
        $where['type'] = 20;//转发操作
        //取得分享所有数据：
        $arr = $this->getAnalyzers(null,$where,array('only_data'=>1,'limit'=>9999999));//var_dump($arr);exit;
        //分享次数
        $outdata['sharecount'] = $arr?count($arr):0;
        //获取该商户的所有中奖名单
        $MExchange = $this->LoadApiModel('exchange');
        $where['status !='] = 0;
        unset($where['type']);
        unset($where['eventid']);
        $arr = $MExchange->GetStoreExchange(null,$where,array('limit'=>9999999,'only_data'=>1));
        $outdata['winCount'] = $arr?count($arr):0;//已兑奖人数
        if($arr)$arr = array_filter($arr,"callExchange");
        $outdata['getCount'] = $arr?count($arr):0;//已兑奖人数
        
        //购买数
        $MOrder = $this->LoadApiModel('order');
        unset($where['status !=']);
        $where['status in']=array(20,40);
        $orderArr = $MOrder->getOrders(null,null,$where,array('limit'=>9999999,'only_data'=>1));
        if($orderArr){
            $outdata['orderCount'] = count($orderArr);
            $outdata['winCount'] +=$outdata['orderCount'];
            $arr = array_filter($orderArr,"callExchange2");
            $outdata['getCount'] +=count($arr);
        }else{
            $outdata['orderCount']=0;
        }
        return $outdata;
    }

    //---------------------------------------数据统计------------------------------start
    //商户助手：有关集阅读的数据统计
    function pluginData($Whr){
        if(empty($Whr['eventid']))exit('错误');

        $where['eventid'] = $Whr['eventid'];
        if(isset($Whr['startdate'])&&$Whr['startdate']) {
            $where['ctime>=']=$Whr['startdate'];
        }
        if(isset($Whr['enddate'])&&$Whr['enddate']){
            $where['ctime<']=  date('Y-m-d H:i:s',strtotime($Whr['enddate'] . "+1 day"));
        }
        //取得所有数据：
        $where['type'] = 10;
        //取得所有数据：
        $arr = $this->getAnalyzers(null,$where,array('only_data'=>1,'limit'=>9999999));
        //访问人次：
        $outdata['browerCount'] =  $arr?array_sum(array_column($arr,'hits')):0;
        //访问人数
        $outdata['joinCount'] =  $arr?count(array_unique(array_column($arr,'userid_from'))):0;
        //参数数
        $outdata['playCount']  = $arr?count(array_unique(array_column($arr,'userid'))):0;

        function callExchange($item){
            return $item['status']==2;
        }
        function callExchange2($item){
            return $item['status']==40;
        }
        //获取该商户的所有中奖名单
        $MExchange = $this->LoadApiModel('exchange');
        $where['status !='] = 0;
        $where['eventid'] = $Whr['eventid'];
        unset($where['type']);
        $arr = $MExchange->GetStoreExchange(null,$where,array('limit'=>9999999,'only_data'=>1));
        $outdata['winCount'] = $arr?count($arr):0;//总中奖人数
        if($arr)$arr = array_filter($arr,"callExchange");
        $outdata['getCount'] = $arr?count($arr):0;//已兑奖人数
        
        //购买数
        $MOrder = $this->LoadApiModel('order');
        unset($where['status !=']);
        $where['status in']=array(20,40);
        $orderArr = $MOrder->getOrders(null,null,$where,array('limit'=>9999999,'only_data'=>1));
        if($orderArr){
            $outdata['orderCount'] = count($orderArr);
            $outdata['winCount'] +=$outdata['orderCount'];
            $arr = array_filter($orderArr,"callExchange2");
            $outdata['getCount'] +=count($arr);
        }else{
            $outdata['orderCount']=0;
        }
        return $outdata;
    }
    
   

};


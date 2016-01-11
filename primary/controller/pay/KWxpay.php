<?php
/**
 * desc: 支付宝支付相关
 *
 *
 *
 *
 *
 *
*/

class KWxpay extends CControllerPys{

    /*
    * desc: 微信准备支付页面
    *
    */
    function actionEntry()
    {
        error_reporting(1);
        $tradeid = $this->get('tradeid');
        $orderid = $this->get('orderid');
        if(!$tradeid && !$orderid){
            $this->display('wxpay-error');
        }

        //支付完成要返回的页面=================================
        if($redirect_url = $this->get('redirect_url')){
            $session = $this->getSession();
            $session->set('redirect_url', $redirect_url);
        }
        //支付完成要返回的页面==============================end

        $MOrder  = $this->LoadApiModel('order');
        $retArr  = $MOrder->getTradeOrderDetail($tradeid, $orderid);
        // echo "$tradeid ===";
        // $this->dump($retArr);
        if(1 == intval($retArr['status'])){
            $orderArr  = $retArr['orders'];
            if(0 == intval($retArr['userid'])){
                //匿名用户
                $addressinfo = $orderArr[0]['addressinfo'];
                $address = json_decode($addressinfo, true);
            }else{
                //注册用户
                $address = $retArr['address'];
            }
            // $payship   = $retArr['payship'];
            // $invoice   = $retArr['invoice'];
            $detailArr = $retArr['order_detail'];
            $body = '';
            foreach($detailArr as $item){
                $body .= $item['title'] . ' ';
            }
            $subject = '购买:'.CMb::mbCut(CTool::RemoveSlashes($body), 30);
            $body = CMb::mbCut($body, 200);

            //写入数据库
            $MPay = $this->LoadApiModel('pay');
            $group_detailArr = $detailArr;
            CTool::table2tree($group_detailArr, 'orderid');
            // $this->dump($group_detailArr);

            foreach($orderArr as $order){
                $_oid = $order['id'];
                $group_goodsArr = $group_detailArr[$_oid]; //此份订单的所有商品
                $title = '购买:';
                foreach($group_goodsArr as $r){
                    $title .= $r['title']. ',';
                }
                $title = trim($title, ',');
                $addArr = array(
                    'orderid' => $_oid,
                    'tradeid' => $order['tradeid'],
                    'userid'  => $order['userid'],
                    'storeid' => $order['storeid'],
                    'title'   => $title,
                    'bankid'  => 30,
                    'bank'    => 'wxpay',
                    'money'   => $order['amount_pay'],
                    'name'    => $address['consignee'],
                    'mobile'  => $address['telphone'],
                    'email'   => $address['email'],
                    'status'  => 10,
                );
                

                $old = $MPay->getPay($order['id']);
                CLog::WriteLog(array($old,$order['id']), 'pay-order');
                if(!$old){
                    $rArr = $MPay->addPay($addArr);
                }

                // $this->dump($rArr);
            }
            //end 写入数据库

            //wx支付相关==================================
            $wxpay = new WxPay();
            $dataArr['title'] = $title;
            $dataArr['money'] = floatval($retArr['total_amount_pay'])*100;
            $dataArr['out_trade_no'] = $tradeid?$tradeid:$orderid;
            $dataArr['payUrl'] = $this->getconfig('HOME_WWW').'/pay/wxpay/?tradeid='.$tradeid;
            $dataArr['notify_url'] = $this->getconfig('HOME_WWW').'/pay/wxpay/notify';
            $code = $this->get('code');
            
            try{
                $jsApiParameters = $wxpay->WxJsPayInterface($code, $dataArr);
                $warning = ob_get_clean();
                if($warning){
                    throw new Exception($warning, 1);
                }
            }catch(Exception $e){
                print $e->getMessage();
                // exit('canceled');
                $this->display('wxpay-error');
            }
            $this->assign('jsApiParameters', $jsApiParameters);
            //wx支付相关===============================end
        }
        $jsApiParametersArr = json_decode($jsApiParameters, true);
        if(isset($jsApiParametersArr['package']) && 'prepay_id='==$jsApiParametersArr['package']){
            // $this->dump($jsApiParametersArr);exit;//prepay_id is null
            $this->display('wxpay-error');
        }
        $this->assign('tradeid', $tradeid);
        $this->assign('orderid', $orderid);
        
        $this->display('wxpay-pre');
    }
    function actionShow()
    {
        $action  = $this->get('action', 0, 'int');
        $tradeid = $this->get('tradeid');
        $orderid = $this->get('orderid');

        $tradeno = $tradeid?$tradeid:$orderid;
        if(!$tradeno || 1 != $action){
            $this->assign('message', '支付途中出现错误');
            $this->display('wxpay-error');
        }

        $MOrder  = $this->LoadApiModel('order');
        $retArr  = $MOrder->getTradeOrderDetail($tradeid, $orderid, null);
        // $this->processByTradeno($tradeno, null);//测试用
        // $this->dump($retArr);
        $storeid = isset($retArr['orders'][0]['storeid'])?$retArr['orders'][0]['storeid']:null;

        $MStore  = $this->LoadApiModel('store');
        $store   = $MStore->getStore($storeid);

        $detailArr = $retArr['order_detail'];

        //支付完成要返回的页面=================================
        $session = $this->getSession();
        if($redirect_url = $session->get('redirect_url')){
            $session->remove('redirect_url');
            $this->location($redirect_url);
            $this->assign('redirect_url', $redirect_url);
        }
        //支付完成要返回的页面==============================end

        $this->assign('store',     $store);
        $this->assign('detailArr', $detailArr);
        $this->assign('tradeno',   $tradeno);
        $this->assign('storeid',   $storeid);
        // $this->dump($detailArr);
        $this->display('wxpay-show');
    }
    /*
    * desc: 微信支付回调页面
    *
    */
    function actionNotify()
    {
        $pay = new WxPay();
        $logs = array();
        $wxinfoArr = $pay->NotifyInterface();
        $logs['wxinfoxml'] = $wxinfoArr['xml'];//log
        $wxinfo = json_decode(json_encode(simplexml_load_string($wxinfoArr['xml'], 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if(1 == intval($wxinfoArr['state'])) {
            //成功
            $tradeno             = $wxinfo['out_trade_no'];
            $logs['addPay-data'] = $wxinfo;
            $logs['tradeno']     = $tradeno;
            $this->processByTradeno($tradeno, $wxinfo);
        }
        CLog::WriteLog($logs, 'wx-pay');
    }

    /*
    * desc: 根据交易号来正理业务逻辑(如减库存，将订单设置成已支付等)
    *
    *@infos --- mix 附加信息
    *
    */
    function processByTradeno($tradeno, $infos=null)
    {
        $MPay = $this->LoadApiModel('pay');
        $MPay->updatePayOrderGoodsCashStatus($tradeno, $infos);
        // $this->dump($retArr);
    }
    //log
    function writeAlipayLogs($msg='')
    {
        ob_start();
        echo "---------POST:\n";
        print_r($_POST);
        echo "----------GET:\n";
        print_r($_GET);
        echo "------message:$msg\n";
        echo "===================================================end\n";
        $logs = ob_get_clean();
        $this->writeWebLog($logs, 'alipay');
    }

};

<?php
/**
 * desc: 子·配置文件
 *
 *
 *
 *
 *
 *
 *
*/

$configGlobalArr = require_once(dirname(__FILE__).'/../../config/main.php');

//=========================子.公共常量=====================================//
//=========================子·公共常量==================================end//


//=========================子.公共变量=====================================//
$INCLUDE_DIRS_SUB   = array(
    BOOT.'/plugin/third/PHPExcel',
);
$INCLUDE_DIRS_ALL   = array_merge($INCLUDE_DIRS_MAIN, $INCLUDE_DIRS_SUB);
//=========================子·公共变量==================================end//


//=========================子·常用设置=====================================//
//如果你需要子域需要和主域不共用session,你可以这样设置
$session_id = 'api'; // md5($CLIENTHOST.$BROWSERID.'primary');
$session_domain = 'api'.TOP_DOMAIN;
ini_set('session.cookie_domain', $session_domain);
//=========================子.常用设置==================================end//

//===============================自动加载==================================//
CAutoLoad::AutoLoad($INCLUDE_DIRS_ALL);
//==============================自动加载================================end//


$configSubArr = array(
    'subApp' => 'api',   
    
    'session_id' => $session_id,
    'session_domain' => $session_domain,
    'session_start'  => false,

    'nickmethods' => array(
    ),
    'routeAlias' => array(
    ),
    
    'user' => array(
    ),
);
// print_r(array_merge_recursive($configGlobalArr, $configSubArr));
return array_merge($configGlobalArr, $configSubArr);


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
$sessionid          = 'primary'; // md5($CLIENTHOST.$BROWSERID.'primary');
$INCLUDE_DIRS_SUB   = array(
    BOOT.'/PHPExcel',
);
$INCLUDE_DIRS_ALL   = array_merge($INCLUDE_DIRS_MAIN, $INCLUDE_DIRS_SUB);
//=========================子·公共变量==================================end//


//=========================子·常用设置=====================================//
//如果你需要子域需要和主域不共用session,你可以这样设置
// echo ini_get('session.cookie_domain');
$session_id = 'primary'; // md5($CLIENTHOST.$BROWSERID.'primary');
$session_domain = TOP_DOMAIN;
ini_set('session.cookie_domain', $session_domain);
//=========================子.常用设置==================================end//


//===============================自动加载==================================//
CAutoLoad::AutoLoad($INCLUDE_DIRS_ALL);
//==============================自动加载================================end//



$configSubArr = array(
    'subApp' => 'primary',
    'ui' => 'general',
    
    // 'sessionid' => $sessionid,

    'nickmethods' => array(
    ),
    'routeAlias' => array(
        
    ),
    
    'user' => array(
        'key' => 'value',
    ),
);

return array_merge_recursive($configGlobalArr, $configSubArr);

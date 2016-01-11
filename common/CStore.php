<?php
/**
 * desc:包含可以各自核销的商户id-主要针对集阅读
 * wpid含义:
 *   18:集阅读
*/

class CStore {
    //可以各自核销的商户id数组
    static $StoreArr = array(3442287);
    //判别商户是否各自核销
    static function CheckStoreWork($storid=null){
        if(!$storid){
            return false;
        }
        $StoreArr = self::$StoreArr;
        return in_array($storid, $StoreArr);
    }
};

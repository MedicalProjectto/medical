<?php
/**
* author: cty@20131101
*   desc: 写日志 
*   
*/

class CLog {

    static $dir = "/tmp";
    static function WriteFile($filename, $logs, $mod='a')
    {
        $fp = fopen($filename, $mod);
        if(!$fp)return false;
        fputs($fp, $logs);
        fclose($fp);
    }
    static function WriteLog($logs, $basename, $mod="a")
    {
        $dir = self::$dir;
        $filename = $dir.'/'.$basename.'.'.date("Ymd").'.log';

        $time = date("Y-m-d.H:i:s");
        ob_start();
        echo ">>>>>>>>>>>>>>>>>>>>({$time})\n";
        print_r($logs);
        echo "\n";
        echo "<<<<<<<<<<<<<<<<<<<<({$time})\n";
        echo "\n";
        $logconent = ob_get_clean();

        self::WriteFile($filename, $logconent, $mod);
    }
    static function WriteError($logs, $basename='error', $mod='a')
    {
        self::WriteLog($logs, $basename, $mod);
    }
};

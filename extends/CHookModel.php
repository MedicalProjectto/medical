<?php
/**
 * author: cty@20121128
 *   desc: 1, 此类的主要目的是解决在自定义的模型中用$this去调用框架的任意方法,
 *            从而实现可以将控制器的方法平滑地移动到模型中去。
 *         2, 当然也可以做一些公共扩展;
 *         3, 此类并不是必须的，但推荐使用;
 *         4, 如果你不需要此类那你可以在你的模型文件中直接继续CModel;
 *
 *
*/
class CHookModel extends CDaoModel {

    protected function writeSql($sqls, $suff='')
    {
        $sqls = str_replace(';', ";\n", $sqls);

        $time = date("Y-m-d.H:i:s");
        $logs = ">>>>>{$time}\n".$sqls."\n{$time}<<<<<\n";

        $this->writeLog($logs, "a", '/tmp', "{$suff}.sql");
    }

    protected function writeAppLog($logs, $prex='app')
    {
        $logs = str_replace(';', ";\n", $logs);

        $time = date("Y-m-d.H:i:s");
        $logs = ">>>>>{$time}\n".$logs."\n{$time}<<<<<\n";

        $this->writeLog($logs, "a", '/tmp', '', $prex);
    }
};

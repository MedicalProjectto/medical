<?php
header("Access-Control-Allow-Origin: *");
$fpcfgs = require(dirname(__FILE__).'/config/sub.php');

Lff::makeApp($fpcfgs)->run();


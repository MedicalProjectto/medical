<?php
$configs = require(dirname(__FILE__).'/config/sub.php');
Lff::makeApp($configs)->run();

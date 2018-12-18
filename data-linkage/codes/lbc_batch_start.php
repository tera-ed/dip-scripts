<?php
require_once('config/config.php');
require_once('common/Utils.php');
//require all php file under process directory
foreach (glob("process/*.php") as $filename){
	require_once $filename;
}

//set root directory
$root = __DIR__;

//if not run by command set default value for arguments
if (!isset($argv)) {
	$argc = 2;
	$argv =array('1', '2,3,4,5,6,7,12,13,14,15,16,17,18,19');
}

//initialize process 0
$process0 = new Process0($argc, $argv);
//execute the processes
$process0->execProcess();

?>
<?php 
require_once('nayose/config/config.php');
require_once('nayose/common/Utils.php');
//require all php file under process directory
foreach (glob("nayose/process/*.php") as $filename){
	require_once $filename;
}

//set root directory
$root = __DIR__;

//if not run by command set default value for arguments
if (!isset($argv)) {
	$argc = 2;
	$argv =array('1', '21,22,23,24,25,26,27,28,29,30,31,32,33,34,35');
}

//initialize process 0
$process0 = new Process0($argc, $argv);
//execute the processes
$process0->execProcess();
?>
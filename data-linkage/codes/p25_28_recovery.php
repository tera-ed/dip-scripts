<?php 
require_once('nayose/config/config.php');
require_once('nayose/common/Recover_Utils.php');
//require all php file under process directory
foreach (glob("nayose/process/*.php") as $filename){
  require_once $filename;
}

//set root directory
$root = __DIR__;

//if not run by command set default value for arguments
if (!isset($argv)) {
  $argc = 3;
  $argv =array('1', '25,26,27,28');
}
$fldName = $argv[2];
//initialize process 99
$process0 = new Process99($argc, $argv);
//execute the processes
$process0->execProcess();
?>
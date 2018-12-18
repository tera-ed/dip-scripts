<?php
/**
 * Acquire the argument array and make sure you can open it again from any place
 *
 * @author Evijoy Jamilan
 *
 */
class Process99{
  private $logger, $mail, $argc, $argv;

  /**
   * Process0 Class Constructor
   * @param int $argc number of arguments passed to the script
   * @param array $argv the arguments as an array. first argument is always the script name
   */
  function __construct($argc, $argv){
    global $procNo;
    $this->logger = new Logger($procNo);
    $this->mail = new Mail();
    $this->argc = $argc;
    $this->argv = $argv;
    $this->fldName = "";
  }
  /**
   * Check if parameter contains integer separated by comma
   * @param string $str
   * @return true/false
   */
  function isValidParams($str){
    $regex = '/^\d+(?:,\d+)*$/';
    return preg_match($regex, $str);
  }

  /**
   * get the processes from parameter
   *
   * @return array/null
   */
  function getParams(){
    $params = null;
    //check if there is parameters
    if($this->argc <= 1){
      $this->logger->error('No parameter.');
    }
    //check number of parameters
    else if($this->argc > 3){
      $this->logger->error('Wrong number parameters.');
    }else{
      //check if parameter is valid
      if($this->isValidParams($this->argv[1])){
        $params = explode(",", $this->argv[1]);
        //sort parameters (ascending)
        asort($params);
      }else{
        $this->logger->error('Invalid Parameter');
      }

      if($this->isValidParams($this->argv[2])){
        //sort parameters (ascending)
        $this->fldName = $this->argv[2];
      }else{
        $params = null;
        $this->logger->error('Invalid Parameter');
      }
    }
    return $params;
  }

  /**
   * create instance and call method of class dynamically
   * @param int $procNo process no
   * @param string $methodName method name
   */
  function callProcessFunc($processNo, $methodName = 'execProcess'){
    global $procNo;
    try {
      $procNo = $processNo;
      $logger = new Logger($procNo);
      $logger->info('START - PROCESS #' . $procNo);
      $className = 'Process'.$procNo;
      $classObj = new $className($logger);

      //call the init method of the specific class
      $result = call_user_func(array($classObj, $methodName));
      $logger->info('END - PROCESS #' . $procNo);
    }catch(Exception $e){
      $logger->info('END - PROCESS #' . $procNo);
      throw $e;
    }
    return $result;
  }

  /**
   * Execute process 0 process
   */
  function execProcess(){
    ini_set('memory_limit', '30G');
    $this->logger->info('START - LBC BATCH');
    try{
      //initialize process 0
      $procList = $this->getParams();

      if($procList){
        $processNo = 0;
        //call process 21 to 34
        for($i = 21; $i <= 34; $i++ ){
          if(in_array($i, $procList)){
            $processNo = $i;
            $this->callProcessFunc($i);
          }
        }
      }
    }catch(Exception $e){
      
      $this->logger->info($e->getMessage());
      //stop processes
      $this->logger->info("Encountered Error at Process #$processNo. Pause process.");
    }
    $this->logger->info('END - LBC BATCH');
  }
}

?>
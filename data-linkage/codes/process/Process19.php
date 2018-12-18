<?php
/**
 * In the end of the process,
 * add 「_comp」 in the end of the name of the used folder
 *
 * @author Evijoy Jamilan
 *
 */
class Process19{
	private $logger, $mail;

	/**
	 * Process19 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process 19
	 */
	function execProcess(){
		try{
			//rename import before directory
			$this->renameDir(getImportPath());
			//rename import after directory
			$this->renameDir(getImportPath(true));
			//rename export before directory
			$this->renameDir(getExportPath());
			//rename export after directory
			$this->renameDir(getExportPath(true));

		}catch (Exception $e){
			$this->logger->error($e->getMessage());
			$this->mail->sendMail();
			throw $e;
		}
	}

	/**
	 * Rename the used folder name
	 * @param string $dir directory
	 * @throws Exception
	 */
	function renameDir($dir){
		try{
			//check if file exist
			if(file_exists($dir)){
				//add 「_comp」 in the end of the name
				$newFoldereName = rtrim($dir, '/').'_comp';
				$result = rename($dir, $newFoldereName);
				if($result){
					$this->logger->info('renamed '.$dir.' to '.$newFoldereName);
				}
			}
		}catch(Exception $e){
			throw $e;
		}
	}
}
?>

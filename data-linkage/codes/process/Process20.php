<?php
/**
 * In the end of the process,
 * add 「_comp」 in the end of the name of the used folder
 *
 * @author Evijoy Jamilan
 *
 */
class Process20{
	private $logger, $mail;

	/**
	 * Process20 Class constructor
	 */
	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	/**
	 * Execute Process20
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
			//add 「_comp」 in the end of the name
			$compFolderePath = rtrim($dir, '/').'_comp';
			$this->moveDir($dir, $compFolderePath);
			// check if Process9 exist
			foreach(glob(rtrim($dir, '/').'_9/') as $d_name){
				$this->moveDir($d_name, $compFolderePath);
			}
		}catch(Exception $e){
			throw $e;
		}
	}
	
	/**
	 * move directory
	 * @param string $dir directory
	 * @param string $compFolderePath backup directory
	 * @throws Exception
	 */
	function moveDir($dir, $compFolderePath){
		try{
			//$this->logger->debug($dir);
			if(glob($dir)){
				foreach(glob($dir.'*/') as $f_name1){
					if (is_dir($f_name1)) {
						$d_name = $f_name1;
						// ディレクトリ名取得
						$subdir_name = basename($f_name1);
						
						foreach(glob($d_name.'*/') as $f_name2){
							$this->movefile($f_name2, $compFolderePath.'/'.$subdir_name);
						}
						$this->rmdirDir($d_name, $compFolderePath);
					} else {
						$this->movefile($f_name1, $compFolderePath);
					}
				}
			}
			$this->rmdirDir($dir, $compFolderePath);
		}catch(Exception $e){
			throw $e;
		}
	}
	
	function movefile($f_name, $compFolderePath){
		$this->logger->debug($f_name);
		$this->mkdirBackupFoldere($compFolderePath);
		/****
		// ファイル名取得
		$file_name =shell_exec('basename '.$f_name);
		// 改行が入るため取る
		$file_name=str_replace(array("\r\n","\n","\r"), '', $file_name);
		// ディレクトリ名
		$dirname = dirname($f_name);
	
		shell_exec('tar zcvf '.escapeshellarg($compFolderePath.'/'.$file_name.'.tar.gz').' -C '.escapeshellarg($dirname).' '.escapeshellarg($file_name).' --remove-files');
		$this->logger->debug('moved and compression '.$f_name.' to '.$compFolderePath);
		*/
		shell_exec('mv -f '.escapeshellarg($f_name).' '.escapeshellarg($compFolderePath));
		$this->logger->debug('moved '.$f_name.' to '.$compFolderePath);
	}
	
	function rmdirDir($dir, $compFolderePath){
		if(glob($dir)){
			// ディレクトリ削除
			if (count(glob($dir.'*/')) == 0) {
				// ディレクトリ削除
				if(rmdir($dir)){
					// 削除成功
					$this->logger->debug('removed '.$dir);
				} else {
					// 削除失敗
					$this->logger->error("ディレクトリ削除に失敗しました. folder name：".$dir);
					throw new Exception("Process20 Failed to remove folders. ".$dir);
				}
				$this->logger->info('renamed '.$dir.' to '.$compFolderePath);
			}
		}
	}
	
	/**
	 * Create comp directory
	 * @param string $compFolderePath comp directory path
	 * @throws Exception
	 */
	function mkdirBackupFoldere($compFolderePath, $permission = 0700){
		if(!glob($compFolderePath)){
			// compディレクトリ作成
			if (!mkdir($compFolderePath, $permission, true)) {
				$this->logger->error("バックアップディレクトリ作成に失敗しました. folder name：".$compFolderePath);
				throw new Exception("Process20 Failed to create folders. ".$compFolderePath);
			} else {
				$this->logger->info('renamed '.$compFolderePath);
			}
		}
	}
}
?>

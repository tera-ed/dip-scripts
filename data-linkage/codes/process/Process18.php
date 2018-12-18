<?php
class Process18{
	private $logger, $mail;

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}
	/**
	 * Execute Process 18
	 */
	function execProcess(){
		try{
			// インポートファイル　エクスポートファイル　ログファイルをbackupファイルに圧縮して保存
			// 対象ファイルは2か月前の物
			// 同名のバックアップファイルが既にあれば処理をしない
			// 運用がうまくいったら元ファイルを削除する処理を入れる rmのコメントアウトを取る

			global $root, $EXPORT_PATH, $IMPORT_PATH, $LOG;

			$backup_folder=array(
					$EXPORT_PATH['before']=>'/'
					,$EXPORT_PATH['after']=>'/'
					,$IMPORT_PATH['before']=>'/'
					,$IMPORT_PATH['after']=>'/'
					,$LOG['path']=>'/'
					,$LOG['err_path']=>''
			);

			foreach($backup_folder as $path => $slash){

				// backupフォルダがない場合には作成
				createDir($root.$path."/backup");
				// 2か月前のデータを取得
				$dir =$root.$path."/".date('Ym',strtotime("-2 month"))."*".$slash;
				//check if file exist
				if(glob($dir)){
					foreach(glob($dir) as $f_name){
						// ファイル名取得
						$file_name =shell_exec('basename '.$f_name);
						// 改行が入るため取る
						$file_name=str_replace(array("\r\n","\n","\r"), '', $file_name);

						// backupされた同一名のファイルがなければbackup処理を行う
						if(!file_exists($root.$path.'/backup/'.$file_name.'.tar.gz')){

							shell_exec('tar zcvf .'.escapeshellarg($path).'/backup/'.$file_name.'.tar.gz .'.escapeshellarg($path).'/'.$file_name.' --remove-files');
							$this->logger->info("backupFileName :".$f_name);

							// 運用がうまくいったら元ファイルを削除する処理を入れる
							//shell_exec('rm -rf '.escapeshellarg($f_name));

						}else{
							// Backup duplicate correspondence
							$this->logger->info("Backup duplicate correspondence backupFileName :".$f_name);
						}
					}
				}else{
					// 対象フォルダ/ファイル無
					// 2017/10/26 ログレベルを修正（ERROR→INFO）
					$this->logger->info("No file/s on export directory.".$dir);
				}
			}


		}catch (Exception $e){
			$this->logger->error($e->getMessage());
			$this->mail->sendMail();
			throw $e;
		}
	}
}
?>
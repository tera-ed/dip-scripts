<?php
class Process1{
	private $logger, $mail;

	function __construct($logger){
		$this->logger = $logger;
		$this->mail = new Mail();
	}

	function check_file_exist($data){
		global  $FTP_IMPORT_PATH,$IMPORT_FILENAME;

		$file_num = 0;
		$data_num = 0;
		$filename="";

		foreach ($data as $key => $dmy){
			foreach($dmy as $val){
				// obicファイルのみ2つ存在するため
				if(is_array($IMPORT_FILENAME[$val])){
					foreach($IMPORT_FILENAME[$val] as $name){
						$dir = $FTP_IMPORT_PATH[$key].'/*_'.$name.'.csv';
						$file_num+=count(glob($dir));
						$data_num++;
					}
				}else{
					// 4半期データ処理処理 マッチング特殊処理
					if($key == 'QuarterCorpMaster'){
						if($val==3){
							//LBC_DATAは複数ある可能性があるので01だけ見る
							$check_filename='/*_'.$IMPORT_FILENAME[$val].'*_01.csv';

						}else{
							$check_filename='/*_'.$IMPORT_FILENAME[$val].'*.csv';
						}
					}else if($key == 'RivalMediaFileHere'){
						$check_filename='/*.xls*';
					}else{
						$check_filename='/*_'.$IMPORT_FILENAME[$val].'.csv';
					}

					$dir = $FTP_IMPORT_PATH[$key].$check_filename;
					$file_num+=count(glob($dir));
					$data_num++;
				}
				
				$this->logger->info($dir);
			}
		}

		if($file_num == $data_num || $file_num == 0){

			return $file_num;
		}

		return -1;
	}

	/**
	 * ファイル移動の準備
	 */
	function file_copy($data){
		global  $FTP_IMPORT_PATH,$IMPORT_FILENAME;

		$path_before = getExportPath();
		$filename="";

		foreach ($data as $key => $dmy){
			foreach($dmy as $val){
				// obicファイルのみ2つ存在するため
				if(is_array($IMPORT_FILENAME[$val])){
					foreach($IMPORT_FILENAME[$val] as $name){
						$dir = $FTP_IMPORT_PATH[$key].'/*_'.$name.'.csv';
						$this->file_copy_run($dir,$key,$val);
					}
				}else{
					// 4半期データ処理処理 マッチング特殊処理
					if($key == 'QuarterCorpMaster'){
						$check_filename='/*_'.$IMPORT_FILENAME[$val].'*.csv';
					}else if($key == 'RivalMediaFileHere'){
						$check_filename='/*.xls*';
					}else{
						$check_filename='/*_'.$IMPORT_FILENAME[$val].'.csv';
					}

					$dir = $FTP_IMPORT_PATH[$key].$check_filename;
					$this->file_copy_run($dir,$key,$val);
				}
			}
		}

		return true;
	}

	/**
	 * ファイル移動の実行
	 */
	function file_copy_run($dir,$type,$val){
		global  $FTP_IMPORT_PATH,$IMPORT_FILENAME;

		$path_before = getImportPath();

		foreach(glob($dir) as $f_name){
			// basename用に空白をエスケープ
			$f_name=str_replace(array(" "), '\ ', $f_name);
			// ファイル名取得
			$file_name =shell_exec('basename '.$f_name);
			// 改行が入るため取る
			$file_name=str_replace(array("\r\n","\n","\r"), '', $file_name);

			// 他媒体特殊処理
			if($type == 'RivalMediaFileHere'){
				$file_name_mv=$IMPORT_FILENAME[$val]."_".$file_name;
			}else{
				$file_name_mv=$file_name;
			}
			// 移動先では空白除去
			$file_name_mv=str_replace(array("\ "," "), '', $file_name_mv);

			// file移動
			shell_exec('mv '.escapeshellarg($FTP_IMPORT_PATH[$type]).'/'.escapeshellarg($file_name). ' '.escapeshellarg($path_before).escapeshellarg($file_name_mv));
			$this->logger->info("ファイルを移動しました。ファイル名：".$file_name);

		}

	}

	/**
	 * Execute Process 1
	 */
	function execProcess(){

		try{
			global $root;

			$quarter_array=array('QuarterCorpMaster'=>array(3,4));
			$week_array=array('CorpMaster'=>array(6,7)
					,'Obic'=>array(8)
					,'RivalMedia'=>array(9)
					,'NGCorp'=>array(10)
			);
			$tabaitai_array=array('RivalMediaFileHere'=>array(11));


			// ファイルの存在チェック
			$quarter_noFiles=$this->check_file_exist($quarter_array);
			$week_noFiles=$this->check_file_exist($week_array);
			$tabaitai_noFiles=$this->check_file_exist($tabaitai_array);


			if($quarter_noFiles==-1 || $week_noFiles==-1){
				// 週次または四半期の組み合わせファイルが揃っていないときにエラー
				throw new Exception("取り込みファイルが揃っていません");
			}else if($quarter_noFiles==0 && $week_noFiles==0 && $tabaitai_noFiles==0){
				//すべての読み込みファイルがない時
				throw new Exception("取み込みファイルがありません。");
			}else{
				// 移動先フォルダがない場合には作成
				$path_before = getImportPath();
				createDir($path_before);

				// 四半期
				if($quarter_noFiles!=0){
					$this->logger->info("四半期のファイルを移動します。");
					$this->file_copy($quarter_array);
				}else{
					$this->logger->info("四半期のファイルはありません。");
				}

				// 週次
				if($week_noFiles!=0){
					$this->logger->info("週次のファイルを移動します。");
					$this->file_copy($week_array);
				}else{
					$this->logger->info("週次のファイルはありません。");
				}

				// 他媒体
				if($tabaitai_noFiles!=0){
					$this->logger->info("他媒体のファイルを移動します。");
					$this->file_copy($tabaitai_array);
				}else{
					$this->logger->info("他媒体のファイルはありません。");
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

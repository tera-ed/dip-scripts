<?php
// ----------------------------
// database configuration
// ----------------------------
// バッチサーバー
$DB['db_server'] = 'dip-vpc03-crai-recolin-db21.ck5id2om0wvt.ap-northeast-1.rds.amazonaws.com';
$DB['db_port'] = '3306';
$DB['db_username'] = 'dipai';
$DB['db_password'] = 'dipeengine';
$DB['db_name'] = 'CRM_BATCH_DB';
$DB['db_type'] = 'mysql';
// 登録サーバー（LBC）
$RECOLIN_DB['db_server'] = 'dip-vpc03-crai-recolin-db21.ck5id2om0wvt.ap-northeast-1.rds.amazonaws.com';
$RECOLIN_DB['db_port'] = '3306';
$RECOLIN_DB['db_username'] = 'dipai';
$RECOLIN_DB['db_password'] = 'dipeengine';
$RECOLIN_DB['db_name'] = 'recolin_aidb01';
$RECOLIN_DB['db_type'] = 'mysql';
// 登録サーバー（CORP）
$CRM_DB['db_server'] = 'dip-vpc03-crapi-db21.ck5id2om0wvt.ap-northeast-1.rds.amazonaws.com';
$CRM_DB['db_port'] = '3306';
$CRM_DB['db_username'] = 'crmapi';
$CRM_DB['db_password'] = 'dipeengine';
$CRM_DB['db_name'] = 'CRMINF';
$CRM_DB['db_type'] = 'mysql';
// 参照サーバー
$RDS_DB['db_server'] = 'dip-vpc03-crm-db02.ck5id2om0wvt.ap-northeast-1.rds.amazonaws.com';
$RDS_DB['db_port'] = '3306';
$RDS_DB['db_username'] = 'root';
$RDS_DB['db_password'] = 'zt9oxahm';
$RDS_DB['db_name'] = 'CRMINF';
$RDS_DB['db_type'] = 'mysql';

// @TODO: Set the default timezone as per your preference
// default value = UTC
$DEFAULT_TIMEZONE = "Asia/Tokyo";
// set create_user_code and update_user_code
$SYSTEM_USER = 'system';
// maximum commit size default value = 1000 (row)
$MAX_COMMIT_SIZE = 1000;
// maximum read size default value = 10000 (row)
#$MAX_READ_SIZE = 100000000;
$MAX_READ_SIZE = 10000;

// ----------------------------
// log settings
// ----------------------------
// appended to process # filename default value = 処理ファイルなし
$LOG['err_filename'] = '処理ファイルなし';
// directory for error log default value = /tmp/errFile
$LOG['err_path'] = '/tmp/errFile';
// directory for process log default value = /log
$LOG['path'] = '/log';
// date format for logs default value = Y-m-d H:i:s
$LOG['date_fmt'] = 'Y-m-d H:i:s';

// ----------------------------
// import settings
// ----------------------------
$IMPORT_FILENAME['3']   = 'LBC_DATA';
$IMPORT_FILENAME['4']   = 'Quarter_Integration';
$IMPORT_FILENAME['5']   = 'LBC_DATA';
$IMPORT_FILENAME['6'][] = 'CRM_Result';
$IMPORT_FILENAME['6'][] = 'LBC_SBNDATA';
$IMPORT_FILENAME['7']   = 'CRM_Result';
$IMPORT_FILENAME['8'][] = 'OBC_KEI_Result';
$IMPORT_FILENAME['8'][] = 'OBC_SEI_Result';
$IMPORT_FILENAME['9']   = 'MDA_Result';
$IMPORT_FILENAME['10']  = 'KNG_Result';
//$IMPORT_FILENAME['11']  = '他媒体';
$IMPORT_FILENAME['11'][]  = 'TABAITAI_';
$IMPORT_FILENAME['11'][]  = 'FORCE_';

$IMPORT_PATH['before'] = '/tmp/csv/Import/before';
$IMPORT_PATH['after'] = '/tmp/csv/Import/after';
$IMPORT_PATH['shellDir'] = '/sh/';

$FTP_IMPORT_PATH['QuarterCorpMaster'] = '/home/teramgmt/tmp/QuarterCorpMaster/Response';
$FTP_IMPORT_PATH['RivalMediaFileHere'] = '/home/teramgmt/tmp/RivalMedia/FileHere';

// ----------------------------
// export settings
// ----------------------------
// export filename for process #9 「他媒体顧客データ」
$EXPORT_FILENAME['11'] = 'FORCE_Result';
// export filename for process #12 「OBIC契約取引先データ」
$EXPORT_FILENAME['12'][] = 'OBC_KEI_Request';
// export filename for process #12 「OBIC請求取引先データ」
$EXPORT_FILENAME['12'][] = 'OBC_SEI_Request';
// export filename for process #13 「CRM顧客差分データ」
$EXPORT_FILENAME['13'] = 'CRM_Request';
// export filename for process #14 「他媒体顧客データ」
$EXPORT_FILENAME['14'] = 'MDA_Request';
// export path before encode conversion
$EXPORT_PATH['before'] = '/tmp/csv/Export/before';
// export path after encode conversion
$EXPORT_PATH['after'] = '/tmp/csv/Export/after';
// csv export encoding default value = UTF-8
$CSV_EXPORT['encoding'] = 'UTF-8';
// csv export date format default value = YmdHis
// appended to export filename, leave blank to remove date from filename
$CSV_EXPORT['date_fmt'] = 'YmdHis';

// ----------------------------
// ftp settings
// ----------------------------
$FTP_IMPORT_PATH['Obic'] = '/home/teramgmt/tmp/Obic/Response';
$FTP_IMPORT_PATH['CorpMaster'] = '/home/teramgmt/tmp/CorpMaster/Response';
$FTP_IMPORT_PATH['NGCorp'] = '/home/teramgmt/tmp/NGCorp/Response';
$FTP_IMPORT_PATH['RivalMedia'] = '/home/teramgmt/tmp/RivalMedia/Response';

$FTP_EXPORT_PATH['Obic'] = '/home/teramgmt/tmp/Obic/Request';
$FTP_EXPORT_PATH['CorpMaster'] = '/home/teramgmt/tmp/CorpMaster/Request';
$FTP_EXPORT_PATH['NGCorp'] = '/home/teramgmt/tmp/NGCorp/Request';
$FTP_EXPORT_PATH['RivalMedia'] = '/home/teramgmt/tmp/RivalMedia/Request';
$FTP_EXPORT_PATH['RivalMediaForce'] = '/home/teramgmt/tmp/RivalMedia/Force';

// ----------------------------
// email settings
// ----------------------------
$MAIL = array(
	'from' => 'ittera.wk@gmail.com',
	'1' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process1 Encountered Error.'),
	'2' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process2 Encountered Error.'),
	'3' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process3 Encountered Error.'),
	'4' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process4 Encountered Error.'),
	'5' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process5 Encountered Error.'),
	'6' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process6 Encountered Error.'),
	'7' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process7 Encountered Error.'),
	'8' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process8 Encountered Error.'),
	'9' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process9 Encountered Error.'),
	'10' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process10 Encountered Error.'),
	'11' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process11 Encountered Error.'),
	'12' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process12 Encountered Error.'),
	'13' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process13 Encountered Error.'),
	'14' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process14 Encountered Error.'),
	'15' => array('to'=>'ju-yashima@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process15 Encountered Error.'),
	'16' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process16 Encountered Error.'),
	'17' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process17 Encountered Error.'),
	'18' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process18 Encountered Error.'),
	'19' => array('to'=>'e-oya@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process19 Encountered Error.'),
);

// ----------------------------
// sequence settings
// ----------------------------
$SEQUENCE['M_CORPORATION_CODE']['prefix'] = "C";
$SEQUENCE['M_CORPORATION_CODE']['lpad'] = true;
$SEQUENCE['M_CORPORATION_CODE']['len'] = 8;
$SEQUENCE['M_CORPORATION_CODE']['pad'] = "0";

$SEQUENCE['MEDIA_CODE']['prefix'] = "M";
$SEQUENCE['MEDIA_CODE']['lpad'] = true;
$SEQUENCE['MEDIA_CODE']['len'] = 9;
$SEQUENCE['MEDIA_CODE']['pad'] = "0";

// If an attempt to convert "ASCII" text to "UTF-8",
// the result will always return in "ASCII".
// It is necessary to force a specific search order for the conversion to work.
// It should be set before using mb_convert_encoding.
if(function_exists('mb_detect_order')) {
	mb_detect_order(array('UTF-8', 'ISO-8859-1'));
}
// set timezone if configured
if(isset($DEFAULT_TIMEZONE) && function_exists('date_default_timezone_set')) {
	@date_default_timezone_set($DEFAULT_TIMEZONE);
}
?>

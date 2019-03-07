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
$CRM_DB['db_server']   = 'dip-vpc03-crapi-db21.ck5id2om0wvt.ap-northeast-1.rds.amazonaws.com';
$CRM_DB['db_port']     = '3306';
$CRM_DB['db_username'] = 'crmapi';
$CRM_DB['db_password'] = 'dipeengine';
$CRM_DB['db_name']     = 'CRMINF';
$CRM_DB['db_type']     = 'mysql';
// 参照サーバー
$RDS_DB['db_server']   = 'dip-vpc03-crapi-db21.ck5id2om0wvt.ap-northeast-1.rds.amazonaws.com';
$RDS_DB['db_port']     = '3306';
$RDS_DB['db_username'] = 'crmapi';
$RDS_DB['db_password'] = 'dipeengine';
$RDS_DB['db_name']     = 'CRMINF';
$RDS_DB['db_type']     = 'mysql';

// @TODO: Set the default timezone as per your preference
// default value = UTC
$DEFAULT_TIMEZONE = "Asia/Tokyo";
// set create_user_code and update_user_code
$SYSTEM_USER = 'system';
$MAX_COMMIT_SIZE = 1000;
// maximum read size default value = 10000 (row)
#$MAX_READ_SIZE = 100000000;
$MAX_READ_SIZE = 10000;

/**
 * Controls process when error/s occur.
 * 1 means to Skip error and proceed to next process. 
 * 0 means to Pause.
 */
$SKIP_FLAG = 1;

// ----------------------------
// log settings
// ----------------------------
// appended to process # filename default value = 処理ファイルなし
$LOG['err_filename'] = '取り込みファイルが揃っていません';
// directory for error log default value = /tmp/errFile
$LOG['err_path'] = '/tmp/errFile/nayose';
// directory for process log default value = /log
$LOG['path'] = '/nayose/log';
// date format for logs default value = Y-m-d H:i:s
$LOG['date_fmt'] = 'Y-m-d H:i:s';


// ----------------------------
// import settings
// ----------------------------
$IMPORT_FILENAME['24'][] = 'CRM_Result_Unique';
$IMPORT_FILENAME['24'][] = 'CRM_Result_Multiple';

$IMPORT_FILENAME['25'][] = 'MDA_Result_Unique';
$IMPORT_FILENAME['25'][] = 'MDA_Result_Multiple';

$IMPORT_FILENAME['26'][] = 'OBC_KEI_Result_Unique';
$IMPORT_FILENAME['26'][] = 'OBC_KEI_Result_Multiple';

$IMPORT_FILENAME['27'][] = 'OBC_SEI_Result_Unique';
$IMPORT_FILENAME['27'][] = 'OBC_SEI_Result_Multiple';

$IMPORT_FILENAME['28'][] = 'KNG_Result_Unique';
$IMPORT_FILENAME['28'][] = 'KNG_Result_Multiple';

$IMPORT_FILENAME['23'] = 'LBC_SBNDATA';

$IMPORT_PATH['before'] = '/tmp/nayose_csv/Import/before';
$IMPORT_PATH['after'] = '/tmp/nayose_csv/Import/after';
$IMPORT_PATH['shellDir'] = '/nayose/sh/';

// ----------------------------
// export settings
// ----------------------------
$EXPORT_FILENAME['23'] = 'LBC_SBNDATA';
$EXPORT_FILENAME['24'] = 'CRM_Result';
$EXPORT_FILENAME['25'] = 'MDA_Result';
$EXPORT_FILENAME['26'] = 'OBC_KEI_Result';
$EXPORT_FILENAME['27'] = 'OBC_SEI_Result';
$EXPORT_FILENAME['28'] = 'KNG_Result';

// export path before encode conversion
$EXPORT_PATH['before'] = '/tmp/nayose_csv/Export/before';
// export path after encode conversion
$EXPORT_PATH['after'] = '/tmp/nayose_csv/Export/after';
// csv export encoding default value = UTF-8
$CSV_EXPORT['encoding'] = 'UTF-8';
// csv export date format default value = YmdHis
// appended to export filename, leave blank to remove date from filename
$CSV_EXPORT['date_fmt'] = 'YmdHis';

$FTP_RESPONSE_PATH['CorpMaster'] = '/home/teramgmt/LS_matching_Request/CorpMaster/Response';
$FTP_RESPONSE_PATH['RivalMedia'] = '/home/teramgmt/LS_matching_Request/RivalMedia/Response';
$FTP_RESPONSE_PATH['Obic'] = '/home/teramgmt/LS_matching_Request/Obic/Response';
$FTP_RESPONSE_PATH['NGCorp'] = '/home/teramgmt/LS_matching_Request/NGCorp/Response';

// ----------------------------
// email settings
// ----------------------------
$MAIL = array(
	'from' => 'ittera.wk@gmail.com',
	'1' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process1 Encountered Error.'),
	'2' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process2 Encountered Error.'),
	'3' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process3 Encountered Error.'),
	'4' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process4 Encountered Error.'),
	'5' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process5 Encountered Error.'),
	'6' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process6 Encountered Error.'),
	'7' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process7 Encountered Error.'),
	'8' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process8 Encountered Error.'),
	'9' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process9 Encountered Error.'),
	'10' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process10 Encountered Error.'),
	'11' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process11 Encountered Error.'),
	'12' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process12 Encountered Error.'),
	'13' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process13 Encountered Error.'),
	'14' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process14 Encountered Error.'),
	'15' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process15 Encountered Error.'),
	'16' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process16 Encountered Error.'),
	'17' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process17 Encountered Error.'),
	'18' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process18 Encountered Error.'),
	'19' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process19 Encountered Error.'),
	'21' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process21 Encountered Error.'),
	'22' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process22 Encountered Error.'),
	'23' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process23 Encountered Error.'),
	'24' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process24 Encountered Error.'),
	'25' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process25 Encountered Error.'),
	'26' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process26 Encountered Error.'),
	'27' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process27 Encountered Error.'),
	'28' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process28 Encountered Error.'),
	'29' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process29 Encountered Error.'),
	'30' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process30 Encountered Error.'),
	'31' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process31 Encountered Error.'),
	'32' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process32 Encountered Error.'),
	'33' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process33 Encountered Error.'),
	'34' => array('to'=>'crm_itinfo@dip-net.co.jp',
				 'subject'=>'DIP Data linkage batch Error List.',
				 'body'=>'Process34 Encountered Error.'),
	
);

// If an attempt to convert "ASCII" text to "UTF-8",
// the result will always return in "ASCII".
// It is necessary to force a specific search order for the conversion to work.
// It should be set before using mb_convert_encoding.
if(function_exists('mb_detect_order')) {
	mb_detect_order(array('UTF-8', 'ISO-8859-1','EUC-JP', 'SJIS'));
}
// set timezone if configured
if(isset($DEFAULT_TIMEZONE) && function_exists('date_default_timezone_set')) {
	@date_default_timezone_set($DEFAULT_TIMEZONE);
}
?>

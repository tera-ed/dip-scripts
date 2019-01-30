#################################################
#! /bin/bash
#
# 作成日付：2016.03.09
# 作成者  ：oya
#
#################################################
export LANG=ja_JP.UTF-8

#basenameは、パスを外し対象のファイルのみを取得する
FILENAME="`basename $0`"

#################################################
#	変数定義
#################################################
# sh格納ディレクトリ
USERDIR="$(cd "$(dirname "${BASH_SOURCE:-${(%):-%N}}")"; pwd)"

# 取込ファイル情報
FILE_PASS=$1

# LOGファイル情報
LOG_PASS=$2
LOG_DIR="${LOG_PASS%/*}"

# テーブル情報
TMP_TBL="t_media_match_wait"

#################################################
#	主処理
#################################################
# DB情報取得
. ${USERDIR}/dip_db_info.conf

cd $USERDIR
(

	DATENOW=$(date "+%Y/%m/%d %H:%M:%S")
	echo "[$DATENOW][INFO]: ${FILENAME} start"

	# クエリ実行
	${MYSQL_DIR}mysql -vvv  --local_infile=1 -u ${MYSQL_USER} --password=${MYSQL_PASS} -h ${MYSQL_HOST} ${MYSQL_DB}<<EOF
-- CSVファイルをインポート
LOAD DATA LOCAL INFILE '${FILE_PASS}'
REPLACE INTO TABLE ${TMP_TBL}
FIELDS
  TERMINATED BY ','
  ENCLOSED BY '"'
  ESCAPED BY '\b'
  (
  @media_code,@compe_media_code,@area_name,@amount,@post_start_date,@data_get_date,@plan,@space,@flag_count,@post_count,@media_type,@ad_type,@job_category,@job_class,@memo,@corporation_name,@zip_code,@addr_prefe,@address1,@address2,@address3,@tel,@section,@corporation_emp_name,@listed_marked,@employee_number,@capital_amount,@year_sales,@dispatch_flag,@introduction_flag,@fax,@business_content,@create_date,@claim_bp_code,@comp_no,@recruit_emp_form,@media_type_details,@corporation_code,@main_code,@sub_code,@job_id
  )
SET
  media_code = nullif(@media_code, ''),
  compe_media_code = nullif(@compe_media_code, ''),
  area_name = nullif(@area_name, ''),
  amount = nullif(@amount, ''),
  post_start_date = nullif(@post_start_date, ''),
  data_get_date = nullif(@data_get_date, ''),
  plan = nullif(@plan, ''),
  space = nullif(@space, ''),
  flag_count = nullif(@flag_count, ''),
  post_count = nullif(@post_count, ''),
  media_type = nullif(@media_type, ''),
  ad_type = nullif(@ad_type, ''),
  job_category = nullif(@job_category, ''),
  job_class = nullif(@job_class, ''),
  memo = nullif(@memo, ''),
  corporation_name = nullif(@corporation_name, ''),
  zip_code = nullif(@zip_code, ''),
  addr_prefe = nullif(@addr_prefe, ''),
  address1 = nullif(@address1, ''),
  address2 = nullif(@address2, ''),
  address3 = nullif(@address3, ''),
  tel = nullif(@tel, ''),
  section = nullif(@section, ''),
  corporation_emp_name = nullif(@corporation_emp_name, ''),
  listed_marked = nullif(@listed_marked, ''),
  employee_number = nullif(@employee_number, ''),
  capital_amount = nullif(@capital_amount, ''),
  year_sales = nullif(@year_sales, ''),
  dispatch_flag = nullif(@dispatch_flag, ''),
  introduction_flag = nullif(@introduction_flag, ''),
  fax = nullif(@fax, ''),
  business_content = nullif(@business_content, ''),
  create_date = nullif(@create_date, ''),
  claim_bp_code = nullif(@claim_bp_code, ''),
  comp_no = nullif(@comp_no, ''),
  recruit_emp_form = nullif(@recruit_emp_form, ''),
  media_type_details = nullif(@media_type_details, '')
;

-- ワーニングを出力する
show warnings;

EOF
	DATENOW=$(date "+%Y/%m/%d %H:%M:%S")
	echo "[$DATENOW][INFO]: ${FILENAME} end"
) >>$LOG_PASS 2>&1

#################################################
#	終了処理
#################################################
if [ $? -eq 1 ]; then
  # FAILED
  exit 9
else
  # SUCCEED
  exit 0
fi

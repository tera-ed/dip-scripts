#################################################
#! /bin/bash
#
# 作成日付：2016.02.24
# 作成者  ：kato
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
TMP_TBL="wk_t_tmp_mda_excel"

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
-- 対象テーブルのTRUNCATE
-- TRUNCATE TABLE ${TMP_TBL};

-- 対象テーブルのDELETE
DELETE FROM ${TMP_TBL};

-- CSVファイルをインポート
LOAD DATA LOCAL INFILE '${FILE_PASS}'
INTO TABLE ${TMP_TBL}
FIELDS
  TERMINATED BY ','
  ENCLOSED BY '"'
  ESCAPED BY '\b'
IGNORE 1 LINES
  (
  @no,@media_name,@post_start_date,@business_content,@fuyokoumoku1,@corporation_name,@zip_code,@addr_prefe,@address1,@address2,@address3,@tel,@section,@corporation_emp_name,@listed_marked,@employee_number,@capital_amount,@year_sales,@space,@job_category,@job_class,@post_count,@dispatch_flag,@introduction_flag,@flag_count,@fax,@data_get_date,@fuyokoumoku2,@hp_url,@representative_name,@fuyokoumoku3,@fuyokoumoku4,@fuyokoumoku5,@fuyokoumoku6,@memo,@fuyokoumoku7,@fuyokoumoku8,@fuyokoumoku9,@fuyokoumoku10
  )
SET
  no                   = nullif(@no, ''),
  media_name           = nullif(@media_name, ''),
  post_start_date      = nullif(@post_start_date, ''),
  business_content     = nullif(@business_content, ''),
  fuyokoumoku1         = nullif(@fuyokoumoku1, ''),
  corporation_name     = nullif(@corporation_name, ''),
  zip_code             = nullif(@zip_code, ''),
  addr_prefe           = nullif(@addr_prefe, ''),
  address1             = nullif(@address1, ''),
  address2             = nullif(@address2, ''),
  address3             = nullif(@address3, ''),
  tel                  = nullif(@tel, ''),
  section              = nullif(@section, ''),
  corporation_emp_name = nullif(@corporation_emp_name, ''),
  listed_marked        = nullif(@listed_marked, ''),
  employee_number      = nullif(@employee_number, ''),
  capital_amount       = nullif(@capital_amount, ''),
  year_sales           = nullif(@year_sales, ''),
  space                = nullif(@space, ''),
  job_category         = nullif(@job_category, ''),
  job_class            = nullif(@job_class, ''),
  post_count           = nullif(@post_count, ''),
  dispatch_flag        = nullif(@dispatch_flag, ''),
  introduction_flag    = nullif(@introduction_flag, ''),
  flag_count           = nullif(@flag_count, ''),
  fax                  = nullif(@fax, ''),
  data_get_date        = nullif(@data_get_date, ''),
  fuyokoumoku2         = nullif(@fuyokoumoku2, ''),
  hp_url               = nullif(@hp_url, ''),
  representative_name  = nullif(@representative_name, ''),
  fuyokoumoku3         = nullif(@fuyokoumoku3, ''),
  fuyokoumoku4         = nullif(@fuyokoumoku4, ''),
  fuyokoumoku5         = nullif(@fuyokoumoku5, ''),
  fuyokoumoku6         = nullif(@fuyokoumoku6, ''),
  memo                 = nullif(@memo, ''),
  fuyokoumoku7         = nullif(@fuyokoumoku7, ''),
  fuyokoumoku8         = nullif(@fuyokoumoku8, ''),
  fuyokoumoku9         = nullif(@fuyokoumoku9, ''),
  fuyokoumoku10        = nullif(@fuyokoumoku10, '')
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

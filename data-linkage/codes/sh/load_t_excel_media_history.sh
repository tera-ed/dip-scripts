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
TMP_TBL="t_excel_media_history"

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
  @media_code,@file_name,@record,@create_date
  )
SET
  media_code = nullif(@media_code, ''),
  file_name = nullif(@file_name, ''),
  record = nullif(@record, ''),
  create_date = nullif(@create_date, '')
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

#################################################
#! /bin/bash
#
# 作成日付	： 2016.10.03
# 作成者  	： Maricris C. Fajutagana
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
TMP_TBL=$3
# バッチ処理番号
BATCH_SYNC_NO=$4

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
INTO TABLE ${TMP_TBL}
FIELDS
  TERMINATED BY ','
  ENCLOSED BY '"'
  ESCAPED BY '\b'
IGNORE 1 LINES
  (
  @billing_code,@office_id,@result_flg,@detail_lvl,@detail_content
  )
SET
  billing_code = nullif(@billing_code, ''),
  office_id = nullif(@office_id, ''),
  result_flg = nullif(@result_flg, ''),
  detail_lvl = nullif(@detail_lvl, ''),
  detail_content = nullif(@detail_content, ''),
  batch_sync_date = now(),
  batch_sync_seq_no = ${BATCH_SYNC_NO}
;

-- ワーニングを出力する
show warnings;

EOF
  # mysql LOAD DATA error check
  if [ $? -eq 1 ]; then
    # FAILED
    exit 1
  fi
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

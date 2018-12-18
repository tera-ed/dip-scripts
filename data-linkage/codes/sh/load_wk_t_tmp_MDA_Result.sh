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
TMP_TBL="wk_t_tmp_mda_result"

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
  @media_code,@office_id,@result_flg,@detail_lvl,@detail_content
  )
SET
  media_code = nullif(@media_code, ''),
  office_id = nullif(@office_id, ''),
  result_flg = nullif(@result_flg, ''),
  detail_lvl = nullif(@detail_lvl, ''),
  detail_content = nullif(@detail_content, '')
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

#################################################
#! /bin/bash
#
# 作成日付：2016.10.03
# 作成者     ：Jonathan Duran
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
	${MYSQL_DIR}mysql -u ${MYSQL_USER} --password=${MYSQL_PASS} -h ${MYSQL_HOST} ${MYSQL_DB}<<EOF

-- CSVファイルをインポート
SELECT 
  office_id,
  head_office_id,
  top_head_office_id,
  top_affiliated_office_id1,
  top_affiliated_office_id2,
  top_affiliated_office_id3,
  top_affiliated_office_id4,
  top_affiliated_office_id5,
  top_affiliated_office_id6,
  top_affiliated_office_id7,
  top_affiliated_office_id8,
  top_affiliated_office_id9,
  top_affiliated_office_id10,
  affiliated_office_id1,
  affiliated_office_id2,
  affiliated_office_id3,
  affiliated_office_id4,
  affiliated_office_id5,
  affiliated_office_id6,
  affiliated_office_id7,
  affiliated_office_id8,
  affiliated_office_id9,
  affiliated_office_id10,
  relation_flag1,
  relation_flag2,
  relation_flag3,
  relation_flag4,
  relation_flag5,
  relation_flag6,
  relation_flag7,
  relation_flag8,
  relation_flag9,
  relation_flag10,
  relation_name1,
  relation_name2,
  relation_name3,
  relation_name4,
  relation_name5,
  relation_name6,
  relation_name7,
  relation_name8,
  relation_name9,
  relation_name10,
  listed_flag,
  listed_name,
  sec_code,
  yuho_number,
  company_stat,
  company_stat_name,
  office_stat,
  office_stat_name,
  move_office_id,
  tousan_date,
  company_vitality,
  company_name,
  company_name_kana,
  office_name,
  company_zip,
  company_pref_id,
  company_city_id,
  company_addr1,
  company_addr2,
  company_addr3,
  company_addr4,
  company_addr5,
  company_addr6,
  company_tel,
  company_fax,
  office_count,
  capital,
  representative_title,
  representative,
  representative_kana,
  industry_code1,
  industry_name1,
  industry_code2,
  industry_name2,
  industry_code3,
  industry_name3,
  license,
  party,
  url,
  tel_cc_flag,
  tel_cc_date,
  move_tel_no,
  fax_cc_flag,
  fax_cc_date,
  move_fax_no,
  inv_date,
  emp_range,
  sales_range,
  income_range
FROM ${TMP_TBL}  
INTO OUTFILE '${FILE_PASS}'
FIELDS TERMINATED BY ','
  OPTIONALLY ENCLOSED BY '"'
  LINES TERMINATED BY '\n'

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

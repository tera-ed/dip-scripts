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
  @office_id,
  @head_office_id,
  @top_head_office_id,
  @top_affiliated_office_id1,
  @top_affiliated_office_id2,
  @top_affiliated_office_id3,
  @top_affiliated_office_id4,
  @top_affiliated_office_id5,
  @top_affiliated_office_id6,
  @top_affiliated_office_id7,
  @top_affiliated_office_id8,
  @top_affiliated_office_id9,
  @top_affiliated_office_id10,
  @affiliated_office_id1,
  @affiliated_office_id2,
  @affiliated_office_id3,
  @affiliated_office_id4,
  @affiliated_office_id5,
  @affiliated_office_id6,
  @affiliated_office_id7,
  @affiliated_office_id8,
  @affiliated_office_id9,
  @affiliated_office_id10,
  @relation_flag1,
  @relation_flag2,
  @relation_flag3,
  @relation_flag4,
  @relation_flag5,
  @relation_flag6,
  @relation_flag7,
  @relation_flag8,
  @relation_flag9,
  @relation_flag10,
  @relation_name1,
  @relation_name2,
  @relation_name3,
  @relation_name4,
  @relation_name5,
  @relation_name6,
  @relation_name7,
  @relation_name8,
  @relation_name9,
  @relation_name10,
  @listed_flag,
  @listed_name,
  @sec_code,
  @yuho_number,
  @company_stat,
  @company_stat_name,
  @office_stat,
  @office_stat_name,
  @move_office_id,
  @tousan_date,
  @company_vitality,
  @company_name,
  @company_name_kana,
  @office_name,
  @company_zip,
  @company_pref_id,
  @company_city_id,
  @company_addr1,
  @company_addr2,
  @company_addr3,
  @company_addr4,
  @company_addr5,
  @company_addr6,
  @company_tel,
  @company_fax,
  @office_count,
  @capital,
  @representative_title,
  @representative,
  @representative_kana,
  @industry_code1,
  @industry_name1,
  @industry_code2,
  @industry_name2,
  @industry_code3,
  @industry_name3,
  @license,
  @party,
  @url,
  @tel_cc_flag,
  @tel_cc_date,
  @move_tel_no,
  @fax_cc_flag,
  @fax_cc_date,
  @move_fax_no,
  @inv_date,
  @emp_range,
  @sales_range,
  @income_range
  )
SET
  office_id = nullif(@office_id, ''),
  head_office_id = nullif(@head_office_id, ''),
  top_head_office_id = nullif(@top_head_office_id, ''),
  top_affiliated_office_id1 = nullif(@top_affiliated_office_id1, ''),
  top_affiliated_office_id2 = nullif(@top_affiliated_office_id2, ''),
  top_affiliated_office_id3 = nullif(@top_affiliated_office_id3, ''),
  top_affiliated_office_id4 = nullif(@top_affiliated_office_id4, ''),
  top_affiliated_office_id5 = nullif(@top_affiliated_office_id5, ''),
  top_affiliated_office_id6 = nullif(@top_affiliated_office_id6, ''),
  top_affiliated_office_id7 = nullif(@top_affiliated_office_id7, ''),
  top_affiliated_office_id8 = nullif(@top_affiliated_office_id8, ''),
  top_affiliated_office_id9 = nullif(@top_affiliated_office_id9, ''),
  top_affiliated_office_id10 = nullif(@top_affiliated_office_id10, ''),
  affiliated_office_id1 = nullif(@affiliated_office_id1, ''),
  affiliated_office_id2 = nullif(@affiliated_office_id2, ''),
  affiliated_office_id3 = nullif(@affiliated_office_id3, ''),
  affiliated_office_id4 = nullif(@affiliated_office_id4, ''),
  affiliated_office_id5 = nullif(@affiliated_office_id5, ''),
  affiliated_office_id6 = nullif(@affiliated_office_id6, ''),
  affiliated_office_id7 = nullif(@affiliated_office_id7, ''),
  affiliated_office_id8 = nullif(@affiliated_office_id8, ''),
  affiliated_office_id8 = nullif(@affiliated_office_id9, ''),
  affiliated_office_id10 = nullif(@affiliated_office_id10, ''),
  relation_flag1 = nullif(@relation_flag1, ''),
  relation_flag2 = nullif(@relation_flag2, ''),
  relation_flag3 = nullif(@relation_flag3, ''),
  relation_flag4 = nullif(@relation_flag4, ''),
  relation_flag5 = nullif(@relation_flag5, ''),
  relation_flag6 = nullif(@relation_flag6, ''),
  relation_flag7 = nullif(@relation_flag7, ''),
  relation_flag8 = nullif(@relation_flag8, ''),
  relation_flag9 = nullif(@relation_flag9, ''),
  relation_flag10 = nullif(@relation_flag10, ''),
  relation_name1 = nullif(@relation_name1, ''),
  relation_name2 = nullif(@relation_name2, ''),
  relation_name3 = nullif(@relation_name3, ''),
  relation_name4 = nullif(@relation_name4, ''),
  relation_name5 = nullif(@relation_name5, ''),
  relation_name6 = nullif(@relation_name6, ''),
  relation_name7 = nullif(@relation_name7, ''),
  relation_name8 = nullif(@relation_name8, ''),
  relation_name9 = nullif(@relation_name9, ''),
  relation_name10 = nullif(@relation_name10, ''),
  listed_flag = nullif(@listed_flag, ''),
  listed_name = nullif(@listed_name, ''),
  sec_code = nullif(@sec_code, ''),
  yuho_number = nullif(@yuho_number, ''),
  company_stat = nullif(@company_stat, ''),
  company_stat_name = nullif(@company_stat_name, ''),
  office_stat = nullif(@office_stat, ''),
  office_stat_name = nullif(@office_stat_name, ''),
  move_office_id = nullif(@move_office_id, ''),
  tousan_date = nullif(@tousan_date, ''),
  company_vitality = nullif(@company_vitality, ''),
  company_name = nullif(@company_name, ''),
  company_name_kana = nullif(@company_name_kana, ''),
  office_name = nullif(@office_name, ''),
  company_zip = nullif(@company_zip, ''),
  company_pref_id = nullif(@company_pref_id, ''),
  company_city_id = nullif(@company_city_id, ''),
  company_addr1 = nullif(@company_addr1, ''),
  company_addr2 = nullif(@company_addr2, ''),
  company_addr3 = nullif(@company_addr3, ''),
  company_addr4 = nullif(@company_addr4, ''),
  company_addr5 = nullif(@company_addr5, ''),
  company_addr6 = nullif(@company_addr6, ''),
  company_tel = nullif(@company_tel, ''),
  company_fax = nullif(@company_fax, ''),
  office_count = nullif(@office_count, ''),
  capital = nullif(@capital, ''),
  representative_title = nullif(@representative_title, ''),
  representative = nullif(@representative, ''),
  representative_kana = nullif(@representative_kana, ''),
  industry_code1 = nullif(@industry_code1, ''),
  industry_name1 = nullif(@industry_name1, ''),
  industry_code2 = nullif(@industry_code2, ''),
  industry_name2 = nullif(@industry_name2, ''),
  industry_code3 = nullif(@industry_code3, ''),
  industry_name3 = nullif(@industry_name3, ''),
  license = nullif(@license, ''),
  party = nullif(@party, ''),
  url = nullif(@url, ''),
  tel_cc_flag = nullif(@tel_cc_flag, ''),
  tel_cc_date = nullif(@tel_cc_date, ''),
  move_tel_no = nullif(@move_tel_no, ''),
  fax_cc_flag = nullif(@fax_cc_flag, ''),
  fax_cc_date = nullif(@fax_cc_date, ''),
  move_fax_no = nullif(@move_fax_no, ''),
  inv_date = nullif(@inv_date, ''),
  emp_range = nullif(@emp_range, ''),
  sales_range = nullif(@sales_range, ''),
  income_range = nullif(@income_range, '')
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

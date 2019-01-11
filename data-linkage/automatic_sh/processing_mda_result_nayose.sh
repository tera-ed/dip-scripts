#!/bin/bash
# processing_mda_result_nayose.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名(取込ファイル 出力ファイル変換文字 取込ファイル日付までの文字数)
MULTIPLE=(${INPUT_FILE_NAME_PATTERN_MAPING_MULTIPLE} ${OUTPUT_FILE_NAME_PATTERN_MAPING_MULTIPLE} 16)
UNIQUE=(${INPUT_FILE_NAME_PATTERN_MAPING_UNIQUE} ${OUTPUT_FILE_NAME_PATTERN_MAPING_UNIQUE} 14)
INPUT_TITLE_HIZUKE_SIZE=14

# 監視ディレクトリ
INPUT_DIR_PATH=${MAPING_RESPONSE_DIR_PATH}

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING3}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_LBC_BACH_START=${FALSE}

#P25ディレクトリ
MOVE_TODAY_DIR=${NAYOSE_IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`

# ----------------------------------

#ファイルをコピー
function copy_gunzip_csv_file {
  my_echo "copy_gunzip_csv_file $1 [$2]"
  input_file_path=$1
  output_file=$2

  # ファイル名
  input_gz_filename=`basename ${input_file_path}`
  input_csv_filename=`basename ${input_file_path} .gz`
  
  #同一解凍ファイル
  remove_file ${MOVE_TODAY_DIR}/${input_csv_filename}
  
  # コピー
  `cp -f ${input_file_path} ${MOVE_TODAY_DIR}/`
  # 解凍
  `gunzip -f ${MOVE_TODAY_DIR}/${input_gz_filename}`
  # 名前変更
  move_input_csv_file ${MOVE_TODAY_DIR}/${input_csv_filename} ${MOVE_TODAY_DIR}/${output_file}
}

# マッチングファイル結果を移動
function maching_csv_file {
  my_echo "process_csv_file_copy"

  if [ ! -e ${MOVE_TODAY_DIR} ]; then
    # 存在しない場合
    mkdir ${MOVE_TODAY_DIR}
  fi

  num_of_csv_files1=`find "${INPUT_DIR_PATH}" -name "${MULTIPLE[0]}*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | wc -l`
  if [ ${num_of_csv_files1} -gt 0 ] ; then
    # 1つでもファイルが存在する場合
    
    # multiple圧縮ファイル検索
    input_multiple_gz_fullpath_array=`find "${INPUT_DIR_PATH}" -name "${MULTIPLE[0]}*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | sort`
    for input_multiple_gz_fullpath in $input_multiple_gz_fullpath_array; do
      # multiple圧縮ファイル名
      input_multiple_gz_filename=`basename ${input_multiple_gz_fullpath}`
      # 日付名
      hizuke_name=${input_multiple_gz_filename:MULTIPLE[2]:INPUT_TITLE_HIZUKE_SIZE}
      
      num_of_csv_files2=`find "${INPUT_DIR_PATH}" -name "${UNIQUE[0]}${hizuke_name}*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | wc -l`
      if [ ${num_of_csv_files2} = 0 ] ; then
        echo "no maching unique csv files. exit. [multiple filename:"${input_multiple_gz_filename}"]"
        continue
      fi

      # 対応するunique圧縮ファイル検索
      input_unique_gz_fullpath=`find ${INPUT_DIR_PATH} -name "${UNIQUE[0]}${hizuke_name}*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | sort | head -n 1`
      # 圧縮ファイルをコピー解凍移動
      copy_gunzip_csv_file ${input_multiple_gz_fullpath} ${hizuke_name}${MULTIPLE[1]}
      copy_gunzip_csv_file ${input_unique_gz_fullpath} ${hizuke_name}${UNIQUE[1]}
      
      # 圧縮ファイルをバックアップへ
      move_input_csv_file_sudo ${input_multiple_gz_fullpath} ${MAPING_RESPONSE_OLD_DIR_PATH}/
      move_input_csv_file_sudo ${input_unique_gz_fullpath} ${MAPING_RESPONSE_OLD_DIR_PATH}/
      # バッチフラグ
      if [ ${IS_LBC_BACH_START} = ${FALSE} ] ; then
        IS_LBC_BACH_START=${TRUE}
      fi
    done
  else
    # 存在しない場合
    echo "no *.csv.gz files. exit."
  fi

}

# ----------------------------------

# 開始
function main {
  echo 'start_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  exit_if_on_processing
  create_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}

  if [ ! -e ${INPUT_DIR_PATH} ]; then
    # 存在しない場合
    echo "no maching response directory. exit."
  else
    # 存在する
    
    num_of_csv_files1=`find "${INPUT_DIR_PATH}" -name "*.csv" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f| wc -l`
    if [ ${num_of_csv_files1} -gt 0 ] ; then
      # 1つでもファイルが存在する場合
      
      # CSVファイル検索
      input_csvfile_fullpath_array=`find "${INPUT_DIR_PATH}" -name "*.csv" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | sort`
      for input_csv_fullpath in $input_csv_fullpath_array; do
        if [ -e ${input_csv_fullpath} ]; then
          # 圧縮
          `sudo -i gzip -f ${input_csv_fullpath}`
          my_echo 'gzip '${input_csv_fullpath}
        fi
      done
    fi
    
    # マッチングディレクトリへ移動
    maching_csv_file
  fi

  if [ ${IS_LBC_BACH_START} = ${TRUE} ] ; then
    # 1つでもファイルが存在する場合
    maching_nayose_lbcsbn_batch_start
  fi

  end_time
}


# ----------------------------------
TODAY_DIR=`date +'%Y%m%d'`
if [ ! -e ${LOG_INPUT_DIR_PATH}/${TODAY_DIR} ]; then
  # 存在しない場合
  mkdir ${LOG_INPUT_DIR_PATH}/${TODAY_DIR}
fi
{
main
}>> ${LOG_INPUT_DIR_PATH}/${TODAY_DIR}/${LOG_FILENAME}

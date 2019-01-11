#!/bin/bash
# processing_mda_result_import.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
#INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_MDA_RES})
INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_MDA_RES} ${INPUT_FILE_NAME_PATTERN_FORCE_RES})

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING2}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_LBC_BACH_START=${FALSE}

#P9ディレクトリ
MOVE_TODAY_DIR=${IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`"_9"

# ----------------------------------

# 対応CSVを移動
function tabaitai_csv_file {
  my_echo "tabaitai_csv_file $1"
  one_dir=$1
  if [ -e ${one_dir} ]; then
    for input_file_name_pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
      if [ -e ${input_csv_fullpath} ]; then
        num_of_csv_files1=`find ${one_dir} -maxdepth 1 -regex ${input_file_name_pattern} -type f | wc -l | sed -e 's/ //g'`
        if [ ${num_of_csv_files1} -gt 0 ] ; then
          # 取込ファイルが存在する

          if [ ! -e ${MOVE_TODAY_DIR} ]; then
            # 存在しない場合
            mkdir ${MOVE_TODAY_DIR}
          fi

          input_csvfile_fullpath_array=`find ${one_dir} -maxdepth 1 -regex ${input_file_name_pattern} -type f | sort`
          for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
             # 移動
            move_input_csv_file ${input_csvfile_fullpath} ${MOVE_TODAY_DIR}/
            # バッチフラグ
            if [ ${IS_LBC_BACH_START} = ${FALSE} ] ; then
              IS_LBC_BACH_START=${TRUE}
            fi
          done
        fi
      fi
    done

    remove_dir ${one_dir}
  fi
}

# ----------------------------------

# 開始
function main {
  echo 'start_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  exit_if_on_processing
  create_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}

  is_processing=${FALSE}
  if [ "$(ls ./${PROCESSING1}* 2>/dev/null)" = '' ] ; then
    is_processing=${TRUE}
    
    # 対応する他媒体ファイル検索
    DIR1=`date +'%Y%m%d'`'_11'
    output_dir_path=${IMPORT_AFTER_DIR_PATH}/${DIR1}
    if [ -e ${output_dir_path} ]; then
      num_of_csv_files=`find ${output_dir_path} -type f -name "${INPUT_FILE_NAME_PATTERN_TABAITAI}" -type f | wc -l`
      if [ ${num_of_csv_files} -gt 0 ] ; then
        echo "already creating_mda_request csv files [path : "${output_dir_path}"]. exit."
        is_processing=${FALSE}
      fi
    fi
  else
    echo "during startup creating_mda_request.sh. exit."
  fi
  
  if [ ${is_processing} = ${TRUE} ] ; then
    # 当日取込結果
    DIR1=`date +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
    # 前日取込結果
    DIR2=`date -d "1 day ago" +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
    # 前日P9未取込
    DIR3=`date -d "1 day ago" +'%Y%m%d'`"_9"
    
    # 監視ディレクトリ
    input_csv_fullpath_array=(${EXPORT_BEFORE_DIR_PATH}/${DIR1} ${EXPORT_BEFORE_DIR_PATH}/${DIR2} ${NAYOSE_EXPORT_BEFORE_DIR_PATH}/${DIR1} ${NAYOSE_EXPORT_BEFORE_DIR_PATH}/${DIR2} ${IMPORT_AFTER_DIR_PATH}/${DIR3})
    for input_csv_fullpath in ${input_csv_fullpath_array[@]}; do
      tabaitai_csv_file ${input_csv_fullpath}
    done
    
    
    IS_LBC_BACH_START=${FALSE}
    if [ ${IS_LBC_BACH_START} = ${TRUE} ] ; then
      # 1つでもファイルが存在する場合
      lbc_maching_batch_start
    else
      # 存在しない場合
      echo "no new csv files. exit."
    fi
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

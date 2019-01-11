#!/bin/bash
# sending_request_to_matching.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_MDA_REQ})

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING4}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_MAPING_BACH_START=${FALSE}

#マッチングディレクトリ
MOVE_TODAY_DIR=${MAPING_REQUEST_DIR_PATH}

# ----------------------------------

# 対応ファイルを移動
function csv_file_move_dir {
  my_echo "csv_file_move_dir $1"
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
            move_input_csv_file_sudo ${input_csvfile_fullpath} ${MOVE_TODAY_DIR}/
            # バッチフラグ
            if [ ${IS_MAPING_BACH_START} = ${FALSE} ] ; then
              IS_MAPING_BACH_START=${TRUE}
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
  
  # 当日取込結果
  DIR1=`date +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
  # 前日取込結果
  DIR2=`date -d "1 day ago" +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
  
  # 監視ディレクトリ
  input_csv_fullpath_array=(${EXPORT_AFTER_DIR_PATH}/${DIR1} ${EXPORT_AFTER_DIR_PATH}/${DIR2})
  for input_csv_fullpath in ${input_csv_fullpath_array[@]}; do
    csv_file_move_dir ${input_csv_fullpath}
  done
  
  if [ ${IS_MAPING_BACH_START} = ${FALSE} ] ; then
    # 存在しない場合
    echo "no new csv files. exit."
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

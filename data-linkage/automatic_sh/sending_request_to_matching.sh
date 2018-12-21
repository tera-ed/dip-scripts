#!/bin/bash
# sending_request_to_matching.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
INPUT_FILE_NAME_PATTERN=${INPUT_FILE_NAME_PATTERN_MDA_REQ}
# 監視ディレクトリ
INPUT_DIR_PATH=${EXPORT_AFTER_DIR_PATH}

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${SENDING_REQUWET_PROCESSING}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

# ----------------------------------

# tey-catch Error
trap catch ERR

# エラー出力
function catch {
    echo CATCH
    end_time
}

# ----------------------------------

# 2つのディレクトリから動作を行う
function csv_file_two_dir {
  my_echo "csv_file_two_dir $1 $2"
  oneday_dir=$1
  twoday_dir=$2
  num_of_csv_files1=`find ${INPUT_DIR_PATH}/${oneday_dir} ${INPUT_DIR_PATH}/${twoday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} | wc -l | sed -e 's/ //g'`
  if [ ${num_of_csv_files1} = 0 ] ; then
    echo "no new csv files. exit."
    end_time
  fi
  
  input_csvfile_fullpath_array=`find ${INPUT_DIR_PATH}/${oneday_dir} ${INPUT_DIR_PATH}/${twoday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
  for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
    # 移動
    move_input_csv_file_sudo ${input_csvfile_fullpath} ${MAPING_REQUEST_DIR_PATH}/
  done
  
  num_of_rmcsv_files=`find ${INPUT_DIR_PATH}/${oneday_dir} ${INPUT_DIR_PATH}/${twoday_dir} -type f | wc -l`
  if [ ${num_of_rmcsv_files} = 0 ] ; then
    rm -rf ${INPUT_DIR_PATH}/${oneday_dir} ${INPUT_DIR_PATH}/${twoday_dir}
    echo 'delete directory. '${INPUT_DIR_PATH}/${oneday_dir}' '${INPUT_DIR_PATH}/${twoday_dir}
  fi
}

# 1つのディレクトリから動作を行う
function csv_file_one_dir {
  my_echo "csv_file_one_dir $1"
  oneday_dir=$1
  num_of_csv_files1=`find ${INPUT_DIR_PATH}/${oneday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} | wc -l | sed -e 's/ //g'`
  if [ ${num_of_csv_files1} = 0 ] ; then
    echo "no new csv files. exit."
    end_time
  fi
  input_csvfile_fullpath_array=`find ${INPUT_DIR_PATH}/${oneday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
  for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
    # 移動
    move_input_csv_file_sudo ${input_csvfile_fullpath} ${MAPING_REQUEST_DIR_PATH}/
  done
  
  num_of_rmcsv_files=`find ${INPUT_DIR_PATH}/${oneday_dir} -type f | wc -l`
  if [ ${num_of_rmcsv_files} = 0 ] ; then
    rm -rf ${INPUT_DIR_PATH}/${oneday_dir}
    echo 'delete directory. '${INPUT_DIR_PATH}/${oneday_dir}
  fi
}

# ----------------------------------

# 終了動作
function end_time {
  delete_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
  
  echo 'end_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  exit
}

# 開始
function main {
  echo 'start_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  
  exit_if_on_processing
  create_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
  
  TODAY_DIR_BACKUP=`date +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
  OLD_TODAY_DIR_BACKUP=`date -d "1 day ago" +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}

  if [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} -a -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
    csv_file_two_dir $OLD_TODAY_DIR_BACKUP $TODAY_DIR_BACKUP
  elif [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} ]; then
    csv_file_one_dir $TODAY_DIR_BACKUP
  elif [ -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
    csv_file_one_dir $OLD_TODAY_DIR_BACKUP
  else
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

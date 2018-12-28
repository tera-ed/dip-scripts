#!/bin/bash
# processing_mda_result_import.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
INPUT_FILE_NAME_PATTERN=${INPUT_FILE_NAME_PATTERN_MDA_RES}
# 監視ディレクトリ
INPUT_DIR_PATH=${NAYOSE_EXPORT_BEFORE_DIR_PATH}

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${MDA_RESULT_INPORT_PROCESSING}
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
  
  MOVE_TODAY_DIR=`date +'%Y%m%d'`"_9"
  if [ ! -e ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR} ]; then
    # 存在しない場合
    mkdir ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}
  fi
  
  is_lbc_bach_start=${FALSE}
  input_csvfile_fullpath_array=`find ${INPUT_DIR_PATH}/${oneday_dir} ${INPUT_DIR_PATH}/${twoday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
  for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
    # 移動
    move_input_csv_file ${input_csvfile_fullpath} ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}/
    # バッチフラグ
    if [ ${is_lbc_bach_start} = ${FALSE} ] ; then
      is_lbc_bach_start=${TRUE}
    fi
  done
  
  if [ ${is_lbc_bach_start} = ${TRUE} ] ; then
    lbc_maching_batch_start
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
  
  MOVE_TODAY_DIR=`date +'%Y%m%d'`"_9"
  if [ ! -e ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR} ]; then
    # 存在しない場合
    mkdir ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}
  fi
  
  is_lbc_bach_start=${FALSE}
  input_csvfile_fullpath_array=`find ${INPUT_DIR_PATH}/${oneday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
  for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
    # 移動
    move_input_csv_file ${input_csvfile_fullpath} ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}/
    # バッチフラグ
    if [ ${is_lbc_bach_start} = ${FALSE} ] ; then
      is_lbc_bach_start=${TRUE}
    fi
  done
  
  if [ ${is_lbc_bach_start} = ${TRUE} ] ; then
    lbc_maching_batch_start
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

  is_processing=${FALSE}
  if [ "$(ls ./${CREATING_PROCESSING}* 2>/dev/null)" = '' ] ; then
    if [ "$(ls ./${MDA_RESULT_NAYOSE_PROCESSING}* 2>/dev/null)" = '' ] ; then
      is_processing=${TRUE}
    else
      echo "during startup processing_mda_result_nayose.sh. exit."
    fi
  else
    echo "during startup creating_mda_request.sh. exit."
  fi
  
  # 対応する他媒体ファイル検索
  output_dir_path=${IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`_11
  if [ -e ${output_dir_path} ]; then
    num_of_csv_files=`find ${output_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN_TABAITAI}" | wc -l`
    if [ ${num_of_csv_files} > 0 ] ; then
      is_processing=${FALSE}
      echo "already creating_mda_request csv files [path : "${output_dir_path}"]. exit."
    fi
  fi
  
  if [ ${is_processing} = ${TRUE} ] ; then
    TODAY_DIR_BACKUP=`date +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
    OLD_TODAY_DIR_BACKUP=`date -d "1 day ago" +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}

    if [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} -a -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
      csv_file_two_dir $TODAY_DIR_BACKUP $OLD_TODAY_DIR_BACKUP
    elif [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} ]; then
      csv_file_one_dir $TODAY_DIR_BACKUP
    elif [ -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
      csv_file_one_dir $OLD_TODAY_DIR_BACKUP
    else
        # 存在しない場合
        echo "no new csv files. exit."
        end_time
    fi

    if [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} ]; then
      num_of_rmcsv_files=`find ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} -type f | wc -l`
      if [ ${num_of_rmcsv_files} = 0 ] ; then
        rm -rf ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP}
        echo 'delete directory. '${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP}
      fi
    fi

    if [ -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
      num_of_rmcsv_files=`find ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} -type f | wc -l`
      if [ ${num_of_rmcsv_files} = 0 ] ; then
        rm -rf ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP}
        echo 'delete directory. '${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP}
      fi
    fi
    end_time
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

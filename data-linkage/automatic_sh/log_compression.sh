#################################################
#! /bin/bash
#
# ログディレクトリを圧縮する
# ファイル名：log_compression.sh
#
# 起動方法：
# bash log_compression.sh
#
#################################################
export LANG=ja_JP.UTF-8

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING="LOG_COMPRESSION"
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}


TODAY_DIR=`date +'%Y%m%d'`
MOVE_TODAY_DIR1=${LOG_INPUT_DIR_PATH}/${TODAY_DIR}
MOVE_TODAY_DIR1=${LOG_INPUT_DIR_PATH}/old

# ----------------------------------

# 開始
function main {
  start_time
  
  num_of_csv_files1=`find ${LOG_INPUT_DIR_PATH}/* -maxdepth 1 -type d -not -path ${MOVE_TODAY_DIR1} -not -path ${MOVE_TODAY_DIR2} | wc -l | sed -e 's/ //g'`
  if [ ${num_of_csv_files1} -gt 0 ] ; then
    # 取込ファイルが存在する
    fullpath_array=`find ${LOG_INPUT_DIR_PATH}/* -maxdepth 1 -type d -not -path ${MOVE_TODAY_DIR1} -not -path ${MOVE_TODAY_DIR2 | sort| sed -e 's/ //g'`
    for input_fullpath in $fullpath_array; do
      gz_file=${input_fullpath}.tar.gz
      
      tar zcf ${gz_file} -C ${input_fullpath} .
      rm -rf ${input_fullpath}/
      debug_echo ${input_fullpath} to ${gz_file}
      # 移動
      move_input_csv_file ${gz_file} ${MOVE_TODAY_DIR1}/

    done
  fi
  
  end_time
}


# ----------------------------------

if [ ! -e ${LOG_INPUT_DIR_PATH}/${TODAY_DIR} ]; then
  # 存在しない場合
  mkdir -p ${LOG_INPUT_DIR_PATH}/${TODAY_DIR}
fi
{
main
}>> ${LOG_INPUT_DIR_PATH}/${TODAY_DIR}/${LOG_FILENAME}

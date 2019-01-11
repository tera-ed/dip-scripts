#!/bin/bash
# move_tabaitai.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_TABAITAI})
#INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_TABAITAI} ${INPUT_FILE_NAME_PATTERN_FORCE})

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING5}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_TABAITAI_START=${FALSE}

#P11ディレクトリ
MOVE_TODAY_DIR=${IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`"_11"

MAX_TORIKOMI_COUNT=1
COUNT=0

# ----------------------------------

# 開始
function move_tabaitai_csv {
  my_echo "move_tabaitai_csv $1 [$2]"
  input_dir=$1
  pattern=$2

  count=0
  num_of_csv_files1=`find ${input_dir} -maxdepth 1 -name ${pattern} -type f | wc -l | sed -e 's/ //g'`
  if [ ${num_of_csv_files1} -gt 0 ] ; then
    # 取込ファイルが存在する
    input_csvfile_fullpath_array=`find ${input_dir} -maxdepth 1 -name ${pattern} -type f | sort`
    for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
      if [ ${COUNT} -ge ${MAX_TORIKOMI_COUNT} ] ; then
        IS_TABAITAI_START=${TRUE}
        break
      fi
      
      # 移動
      move_input_csv_file ${input_csvfile_fullpath} ${MOVE_TODAY_DIR}/
      COUNT=$(( COUNT + 1 ))
    done
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
      for input_file_name_pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
        num_of_csv_files=`find ${output_dir_path} -type f -name "${input_file_name_pattern}" -type f | wc -l`
        COUNT=$(( COUNT + ${num_of_csv_files} ))
        
        if [ ${COUNT} -ge ${MAX_TORIKOMI_COUNT} ] ; then
          echo "already creating_mda_request csv files [path : "${output_dir_path}"]. exit."
          is_processing=${FALSE}
          break
        fi
      done
    fi
  else
    echo "during startup creating_mda_request.sh. exit."
  fi

  if [ ${is_processing} = ${TRUE} ] ; then

    if [ ! -e ${MOVE_TODAY_DIR} ]; then
      # 存在しない場合
      mkdir ${MOVE_TODAY_DIR}
    fi

    # 前日P11未取込
    DIR1=`date -d "1 day ago" +'%Y%m%d'`"_11"
    input_csv_fullpath_array=(${IMPORT_AFTER_DIR_PATH}/${DIR1})

    for input_file_name_pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
      
      for input_csv_fullpath1 in ${input_csv_fullpath_array[@]}; do
        if [ -e ${input_csv_fullpath1} ]; then
          if [ ${IS_TABAITAI_START} = ${TRUE} ] ; then
            break
          fi
          move_tabaitai_csv ${input_csv_fullpath1} ${input_file_name_pattern}
          remove_dir ${input_csv_fullpath1}
        fi
      done

      for input_csv_fullpath2 in ${TABAITAI_DIR_PATHS[@]}; do
        if [ -e ${input_csv_fullpath2} ]; then
          if [ ${IS_TABAITAI_START} = ${TRUE} ] ; then
            break
          fi
          move_tabaitai_csv ${input_csv_fullpath2} ${input_file_name_pattern}
        fi
      done
    done
  fi

  remove_dir ${MOVE_TODAY_DIR}
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

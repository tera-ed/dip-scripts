#################################################
#! /bin/bash
#
# 他媒体ファイル移動
# 対応するcsv を PHP対応パス(../data-linkage-nayose/codes/tmp/csv/Import/after/日付(yyyyMMdd)_11)へ移動
# ディレクトリパスを確認して起動
# ファイル名：get_tabaitai.sh
#
# 起動方法：
# bash get_tabaitai.sh
#
#################################################
export LANG=ja_JP.UTF-8

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_TABAITAI} ${INPUT_FILE_NAME_PATTERN_FORCE})

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING8}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_TABAITAI_START=${FALSE}

#P11ディレクトリ
MOVE_TODAY_DIR=${IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`"_11"

MAX_TORIKOMI_COUNT=1
COUNT=0

# 監視ディレクトリ
input_ok_path=${PYTHON_SOURCE_OK_PATH}
input_done_path=${PYTHON_SOURCE_DONE_PATH}

# 起動ソース
SOURCE_PATHE=${FILE_TABAITAI_UPLOAD_PATH}
SOURCE_NAME=${FILE_TABAITAI_UPLOAD_SOURCE}

# ----------------------------------

# 開始
function main {
  start_time

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
          info_echo "already creating_mda_request csv files [path : "${output_dir_path}"]. exit."
          is_processing=${FALSE}
          break
        fi
      done
    fi
  else
    info_echo "during startup creating_mda_request.sh. exit."
  fi
  
  if [ ${is_processing} = ${TRUE} ] ; then
    make_dir ${MOVE_TODAY_DIR}
    
    # 前日P11未取込
    DIR1=`date -d "1 day ago" +'%Y%m%d'`"_11"
    input_csv_fullpath_array=(${IMPORT_AFTER_DIR_PATH}/${DIR1})

    for pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
      # 過去未取込CSV
      for input_csv_fullpath in ${input_csv_fullpath_array[@]}; do
        input_csvfile_fullpath_array=`find ${input_csv_fullpath} -maxdepth 1 -name ${pattern} -type f 2>/dev/null | sort`
        for input_csv_path in $input_csvfile_fullpath_array; do
          #debug_echo ${input_csv_path}
          
          # 移動
          move_input_csv_file ${input_csv_path} ${MOVE_TODAY_DIR}/
          COUNT=$(( COUNT + 1 ))
        done
        remove_dir ${input_csv_fullpath}
      done
    done
    
    if [ ${COUNT} -gt 0 ] ; then
      # 過去CSVが存在する
      is_processing=${FALSE}
    fi
  fi
  
  if [ ${is_processing} = ${TRUE} ] ; then
    for pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
      if [ ${is_processing} = ${FALSE} ] ; then
        break
      fi
      
      # 取込ファイルが存在する
      cmd1="find "${input_ok_path}" -maxdepth 1 -name '"${pattern}"' -type f 2>/dev/null | wc -l | sed -e 's/ //g'"
      num_of_csv_files=`sudo -u ${PYTHON_USER} sh -c "${cmd1}"`
      if [ ${num_of_csv_files} -gt 0 ] ; then
        # 取込ファイルが存在する
        cmd2="find "${SOURCE_PATHE}/${SOURCE_NAME}" 2>/dev/null | wc -l"
        num_of_files=`sudo -u ${PYTHON_USER} sh -c "${cmd2}"`
        if [ ${num_of_files} -gt 0 ] ; then
          bach_data=$(sudo -u ${PYTHON_USER} sh -c "cd ${SOURCE_PATHE} && bash ${SOURCE_NAME}")
          if [ -z ${bach_data} ] ; then
            info_echo "success "${SOURCE_NAME}
          else
            error_echo "failed "${SOURCE_NAME}
          fi
        else
          error_echo " not source : ${SOURCE_PATHE}/${SOURCE_NAME}"
        fi
      fi
      
      # 圧縮ファイル
      cmd1="find "${input_done_path}" -maxdepth 1 -name *"${pattern}".zip -type f 2>/dev/null | sort"
      input_zipfile_fullpath_array=`sudo -u ${PYTHON_USER} sh -c "${cmd1}"`
      for input_zip_path in $input_zipfile_fullpath_array; do
         #debug_echo ${input_zip_path}
         # ファイル名
         filename=`basename ${input_zip_path}`
         # 移動
         move_input_csv_file_sudo ${input_zip_path} ${MOVE_TODAY_DIR}/
         
         # 解凍
         zip_info=`unzip -o ${MOVE_TODAY_DIR}/${filename} -d ${MOVE_TODAY_DIR}/`
         debug_echo ${zip_info}
         
         # ZIP削除
         remove_file ${MOVE_TODAY_DIR}/${filename}
         COUNT=$(( COUNT + 1 ))
         
         if [ ${COUNT} -ge ${MAX_TORIKOMI_COUNT} ] ; then
           info_echo "already creating_mda_request csv files. exit."
           is_processing=${FALSE}
           break
         fi
      done
    done
  fi
  
  if [ ${COUNT} = 0 ] ; then
    # 存在しない場合
    info_echo "no tabaitai csv files. exit."
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

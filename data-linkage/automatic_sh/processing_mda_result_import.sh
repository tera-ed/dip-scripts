#################################################
#! /bin/bash
#
# 次世代マッチング結果取込 自動化(P9)
# 対応パス(../data-linkage-nayose/codes/tmp/nayose_csv/Export/before/日付(yyyyMMdd)_comp)から対応ファイル(*_MDA_Result.csv)を
# 対応パス(../data-linkage-nayose/codes/tmp/csv/Import/after/日付(yyyyMMdd)_9)へ移動し、Process9バッチを起動
# ファイル名：processing_mda_result_import.sh
#
# 起動方法：
# bash processing_mda_result_import.sh
#
#################################################
export LANG=ja_JP.UTF-8

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_MDA_RES} ${INPUT_FILE_NAME_PATTERN_FORCE_RES})
TABAITAI_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_TABAITAI} ${INPUT_FILE_NAME_PATTERN_FORCE})

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
  debug_echo "tabaitai_csv_file $1"
  one_dir=$1
  
  if [ -e ${one_dir} ]; then
    for input_file_name_pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
      num_of_csv_files1=`find ${one_dir} -maxdepth 1 -regex ${input_file_name_pattern} -type f | wc -l | sed -e 's/ //g'`
      if [ ${num_of_csv_files1} -gt 0 ] ; then
        # 取込ファイルが存在する
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
    done
  fi
  
  remove_dir ${one_dir}
}

# ----------------------------------

# 開始
function main {
  start_time

  is_processing=${FALSE}
  if [ "$(ls ./${PROCESSING1}* 2>/dev/null)" = '' ] ; then
    if [ "$(ls ./${PROCESSING7}* 2>/dev/null)" = '' ] ; then
      is_processing=${TRUE}
      
      # 対応する他媒体ファイル検索
      DIR1=`date +'%Y%m%d'`'_11'
      output_dir_path=${IMPORT_AFTER_DIR_PATH}/${DIR1}
      if [ -e ${output_dir_path} ]; then
        for input_file_name_pattern in ${TABAITAI_FILE_NAME_PATTERNS[@]}; do
          num_of_csv_files1=`find ${output_dir_path} -maxdepth 1 -name ${input_file_name_pattern}  -type f | wc -l | sed -e 's/ //g'`
          if [ ${num_of_csv_files1} -gt 0 ] ; then
            # 取込ファイルが存在する
            info_echo "already creating_mda_request csv files [path : "${output_dir_path}"]. exit."
            is_processing=${FALSE}
            break
          fi
        done
      fi
    else
      info_echo "during startup crm_src_cfl.sh. exit."
    fi
  else
    info_echo "during startup creating_mda_request.sh. exit."
  fi
  
  is_python=${FALSE}
  if [ ${is_processing} = ${TRUE} ] ; then
    num_of_python=`ps aux | grep python3 2>/dev/null | grep src/main_excel.py 2>/dev/null | grep -v grep | wc -l`
    if [ ${num_of_python} -gt 0 ] ; then
      info_echo "during startup "${PYTHON_SOURCE_DIR_PATH}"/src/main_excel.py exit."
    else
      is_python=${TRUE}
    fi
  fi
  
  if [ ${is_python} = ${TRUE} ] ; then
    make_dir ${MOVE_TODAY_DIR}
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
    
    if [ -e ${MOVE_TODAY_DIR} -a ${IS_LBC_BACH_START} = ${FALSE} ]; then
      for input_file_name_pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
        num_of_csv_files1=`find ${MOVE_TODAY_DIR} -maxdepth 1 -regex ${input_file_name_pattern} -type f | wc -l | sed -e 's/ //g'`
        if [ ${num_of_csv_files1} -gt 0 ] ; then
          # 取込ファイルが存在する
          IS_LBC_BACH_START=${TRUE}
        fi
      done
    fi
  fi

  if [ ${IS_LBC_BACH_START} = ${TRUE} ] ; then
    # 1つでもファイルが存在する場合
    lbc_maching_batch_start
  else
    # 存在しない場合
    info_echo "no new csv files. exit."
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

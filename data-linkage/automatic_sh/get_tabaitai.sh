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
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING5}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_TABAITAI_START=${FALSE}

#P11ディレクトリ
MOVE_TODAY_DIR=${IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`"_11"

COUNT=0

# 監視ディレクトリ
input_ok_path=${PYTHON_SOURCE_OK_PATH}
input_done_path=${PYTHON_SOURCE_DONE_PATH}

# ----------------------------------

# 開始
function main {
  start_time

  is_processing=${FALSE}
  if [ "$(ls ./${PROCESSING1}* 2>/dev/null)" = '' ] ; then
    is_processing=${TRUE}
  else
    info_echo "during startup creating_mda_request.sh. exit."
  fi

  if [ ${is_processing} = ${TRUE} ] ; then
    num_of_csv_files=`find ${input_done_path} -maxdepth 1 -type d 2>/dev/null | wc -l | sed -e 's/ //g'`
    if [ ${num_of_csv_files} = 0 ] ; then
      `sudo -i mkdir -p ${input_done_path}`
      `sudo -i chown dip-sysinfo:dip-sysinfo ${input_done_path}`
    fi
    
    make_dir ${MOVE_TODAY_DIR}
    # 前日P11未取込
    DIR1=`date -d "1 day ago" +'%Y%m%d'`"_11"
    input_csv_fullpath_array=(${IMPORT_AFTER_DIR_PATH}/${DIR1})

    for pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do

      
      # 取込ファイルが存在する
      input_csvfile_fullpath_array=`sudo -i find ${input_ok_path} -maxdepth 1 -name ${pattern} -type f 2>/dev/null | sort`
      for input_csv_path in $input_csvfile_fullpath_array; do
        debug_echo ${input_csv_path}
        
        # ファイル名
        filename=`basename ${input_csv_path}`
        # 圧縮ファイル名
        gz_filename=${filename}".gz"
        
        # 圧縮
        `sudo -i gzip -f ${input_csv_path}`
        # コピー
        `sudo -i cp -f ${input_ok_path}/${gz_filename} ${MOVE_TODAY_DIR}/`
        # バックアップへ移動
        move_input_csv_file_sudo ${input_ok_path}/${gz_filename} ${input_done_path}
        # 解凍
        `gunzip -f ${MOVE_TODAY_DIR}/${gz_filename}`

        COUNT=$(( COUNT + 1 ))
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

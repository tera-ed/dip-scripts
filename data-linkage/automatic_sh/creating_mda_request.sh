#################################################
#! /bin/bash
#
# 他媒体取込のみ 自動化(P11)
# PHP対応パス(../data-linkage-nayose/codes/tmp/csv/Import/after/日付(yyyyMMdd)_11)から
# 取込他媒体CSVがあればProcess11バッチを起動
# ファイル名：creating_mda_request.sh
#
# 起動方法：
# bash creating_mda_request.sh
#
#################################################
export LANG=ja_JP.UTF-8

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# 読込ファイルパターン名
#INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_TABAITAI})
INPUT_FILE_NAME_PATTERNS=(${INPUT_FILE_NAME_PATTERN_TABAITAI} ${INPUT_FILE_NAME_PATTERN_FORCE})

# 起動有無
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# 起動有無パターン名
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING1}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

#PHPバッチ起動
IS_LBC_BACH_START=${FALSE}

#P11ディレクトリ
MOVE_TODAY_DIR=${IMPORT_AFTER_DIR_PATH}/`date +'%Y%m%d'`"_11"

# ----------------------------------

# 開始
function main {
  start_time

  is_processing=${FALSE}
  if [ "$(ls ./${PROCESSING5}* 2>/dev/null)" = '' ] ; then
    if [ "$(ls ./${PROCESSING2}* 2>/dev/null)" = '' ] ; then
      is_processing=${TRUE}
    else
      info_echo "during startup processing_mda_result_import.sh. exit."
    fi
  else
    info_echo "during startup get_tabaitai.sh. exit."
  fi

  if [ ${is_processing} = ${TRUE} ] ; then
    # 当日P11未取込
    DIR1=`date +'%Y%m%d'`"_11"

    if [ -e ${MOVE_TODAY_DIR} ]; then
      for input_file_name_pattern in ${INPUT_FILE_NAME_PATTERNS[@]}; do
        num_of_csv_files1=`find ${MOVE_TODAY_DIR} -maxdepth 1 -name ${input_file_name_pattern}  -type f | wc -l | sed -e 's/ //g'`
        if [ ${num_of_csv_files1} -gt 0 ] ; then
            # 取込ファイルが存在する
            
            # バッチフラグ
            if [ ${IS_LBC_BACH_START} = ${FALSE} ] ; then
              IS_LBC_BACH_START=${TRUE}
              break
            fi
        fi
        
      done

      remove_dir ${MOVE_TODAY_DIR}
    fi

    if [ ${IS_LBC_BACH_START} = ${TRUE} ] ; then
      # 1つでもファイルが存在する場合
      tabaitai_torikomi_batch_start
    else
      # 存在しない場合
      info_echo "no new csv files. exit."
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

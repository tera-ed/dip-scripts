#################################################
#! /bin/bash
#
# S3からファイルをダウンロード
# ファイル名：crm_file_transacter.sh.sh
#
# 起動方法：
# bash crm_file_transacter.sh.sh
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
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING6}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

# 起動ソース
SOURCE_PATHE=${FILE_TRANSACTER_DIR_PATH}
SOURCE_NAME=${FILE_TRANSACTER_SOURCE}

# ----------------------------------

# 開始
function main {
  start_time

  is_processing=${FALSE}
  if [ "$(ls ./${PROCESSING7}* 2>/dev/null)" = '' ] ; then
    if [ "$(ls ./${PROCESSING8}* 2>/dev/null)" = '' ] ; then
      is_processing=${TRUE}
    else
      info_echo "during startup get_tabaitai.sh. exit."
    fi
  else
    info_echo "during startup crm_src_cfl.sh. exit."
  fi

  is_python=${FALSE}  
  if [ ${is_processing} = ${TRUE} ] ; then
    num_of_python=`ps aux | grep python3 2>/dev/null | grep src/file_transacter.py 2>/dev/null | grep -v grep | wc -l`
    if [ ${num_of_python} -gt 0 ] ; then
      info_echo "during startup "${FILE_TRANSACTER_DIR_PATH}"/src/file_transacter.py exit."
    else
      num_of_python=`ps aux | grep python3 2>/dev/null | grep src/main_excel.py 2>/dev/null | grep -v grep | wc -l`
      if [ ${num_of_python} -gt 0 ] ; then
        info_echo "during startup "${PYTHON_SOURCE_DIR_PATH}"/src/main_excel.py exit."
      else
        is_python=${TRUE}
      fi
    fi
  fi

  if [ ${is_python} = ${TRUE} ] ; then
    num_of_files=`sudo -u ${PYTHON_USER} find ${SOURCE_PATHE}/${SOURCE_NAME} 2>/dev/null | wc -l`
    if [ ${num_of_files} -gt 0 ] ; then
       bach_data=$(sudo -u ${PYTHON_USER} sh -c "cd ${SOURCE_PATHE} && bash ${SOURCE_NAME}")
       if [ -z ${bach_data} ] ; then
         info_echo "success "${SOURCE_NAME}
       else
         error_echo $bach_data
       fi
    else
      error_echo " not directory : ${SOURCE_PATHE}/${SOURCE_NAME}"
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

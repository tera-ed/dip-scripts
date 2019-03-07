#################################################
#! /bin/bash
#
# 対応CSVファイルが存在する場合pythonを起動する
#
# 起動方法：
# bash crm_src_cfl.sh
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
PREFIX_OF_FILENAME_ON_PROCESSING=${PROCESSING7}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#ログファイル名
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

# 起動ソース
SOURCE_PATHE=${PYTHON_SOURCE_DIR_PATH}
SOURCE_NAME=${PYTHON_SOURCE_SOURCE}

INPUT_PATH=${PYTHON_SOURCE_INPUT_PATH}

# ----------------------------------

# 開始
function main {
  start_time

  is_processing=${FALSE}
  if [ "$(ls ./${PROCESSING6}* 2>/dev/null)" = '' ] ; then
    if [ "$(ls ./${PROCESSING5}* 2>/dev/null)" = '' ] ; then
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
    cmd1="find "${INPUT_PATH}"/* -not -name .gitkeep -maxdepth 1 -type f 2>/dev/null | wc -l | sed -e 's/ //g'"
    num_of_csv_files=`sudo -u ${PYTHON_USER} sh -c "${cmd1}"`
    if [ ${num_of_csv_files} -gt 0 ] ; then
      # 取込ファイルが存在する
      cmd2="find "${SOURCE_PATHE}/${SOURCE_NAME}" 2>/dev/null | wc -l"
      num_of_files=`sudo -u ${PYTHON_USER} sh -c "${cmd2}"`
      if [ ${num_of_files} -gt 0 ] ; then
        sudo -u ${PYTHON_USER} sh -c "cd ${SOURCE_PATHE} && bash ${SOURCE_NAME} 2>/dev/null"
      else
        error_echo " not directory : ${SOURCE_PATHE}/${SOURCE_NAME}"
      fi
    else
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

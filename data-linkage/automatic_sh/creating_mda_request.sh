#!/bin/bash
# creating_mda_request.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# �Ǎ��t�@�C���p�^�[����
INPUT_FILE_NAME_PATTERN=${INPUT_FILE_NAME_PATTERN_TABAITAI}
# �Ď��f�B���N�g��
INPUT_DIR_PATH=${IMPORT_AFTER_DIR_PATH}

# �N���L��
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# �N���L���p�^�[����
PREFIX_OF_FILENAME_ON_PROCESSING=${CREATING_PROCESSING}
FILENAME_ABOUT_PROCESSING=${PREFIX_OF_FILENAME_ON_PROCESSING}"_"`date +'%Y%m%d%H%M%S'`

#���O�t�@�C����
LOG_FILENAME=${PREFIX_OF_FILENAME_ON_PROCESSING}${LOGFILE_SUFFIX}

# ----------------------------------

# tey-catch Error
trap catch ERR

# �G���[�o��
function catch {
    echo CATCH
    end_time
}

# ----------------------------------

# PHP�o�b�`����
function lbc_bach_start {
  my_echo "lbc_bach_start"
  bach_data=$(cd /home/teramgmt/temp/data-linkage-nayose/codes; /usr/bin/php lbc_batch_start.php 0,11,14,15,18,19)
  if [ -z ${bach_data} ] ; then
    echo "success lbc_batch_start.php"
  else
    echo $bach_data
  fi
}

# ----------------------------------

# �I������
function end_time {
  delete_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
  
  echo 'end_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  exit
}

# �J�n
function main {
  echo 'start_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  
  exit_if_on_processing
  create_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
  
  num_of_csv_files=0
  input_dir_path=${INPUT_DIR_PATH}/`date +'%Y%m%d'`_11
  if [ -e ${input_dir_path}/${ERROR_DIR_NAME} ]; then
    # �G���[�t�H���_�L
    num_of_csv_files=`find ${input_dir_path} -name "*.csv" -not -path "${input_dir_path}/${ERROR_DIR_NAME}/*" -type f -name "${INPUT_FILE_NAME_PATTERN}" | wc -l`
  elif [ -e ${input_dir_path} ]; then
    num_of_csv_files=`find ${input_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN}" | wc -l`
  fi
  
  if [ ${num_of_csv_files} = 0 ] ; then
    # ���݂��Ȃ��ꍇ
    echo "no new csv files. exit."
  else
    lbc_bach_start
  fi
  end_time
}


# ----------------------------------
TODAY_DIR=`date +'%Y%m%d'`
if [ ! -e ${LOG_INPUT_DIR_PATH}/${TODAY_DIR} ]; then
  # ���݂��Ȃ��ꍇ
  mkdir ${LOG_INPUT_DIR_PATH}/${TODAY_DIR}
fi
{
main
}>> ${LOG_INPUT_DIR_PATH}/${TODAY_DIR}/${LOG_FILENAME}
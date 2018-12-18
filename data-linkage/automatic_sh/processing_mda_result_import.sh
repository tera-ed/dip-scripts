#!/bin/bash
# processing_mda_result_import.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# �Ǎ��t�@�C���p�^�[����
INPUT_FILE_NAME_PATTERN=${INPUT_FILE_NAME_PATTERN_MDA_RES}
# �Ď��f�B���N�g��
INPUT_DIR_PATH=${NAYOSE_EXPORT_BEFORE_DIR_PATH}

# �N���L��
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# �N���L���p�^�[����
PREFIX_OF_FILENAME_ON_PROCESSING=${MDA_RESULT_INPORT_PROCESSING}
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
  bach_data=$(cd /home/teramgmt/temp/data-linkage-nayose/codes; /usr/bin/php lbc_batch_start.php 0,9,20)
  if [ -z ${bach_data} ] ; then
    echo "success lbc_batch_start.php"
  else
    echo $bach_data
  fi
}

# ----------------------------------

# 2�̃f�B���N�g�����瓮����s��
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
    # ���݂��Ȃ��ꍇ
    mkdir ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}
  fi
  
  is_lbc_bach_start=${FALSE}
  input_csvfile_fullpath_array=`find ${INPUT_DIR_PATH}/${oneday_dir} ${INPUT_DIR_PATH}/${twoday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
  for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
    # �ړ�
    move_input_csv_file ${input_csvfile_fullpath} ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}/
    # �o�b�`�t���O
    if [ ${is_lbc_bach_start} = ${FALSE} ] ; then
      is_lbc_bach_start=${TRUE}
    fi
  done
  
  if [ ${is_lbc_bach_start} = ${TRUE} ] ; then
    lbc_bach_start
  fi
}

# 1�̃f�B���N�g�����瓮����s��
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
    # ���݂��Ȃ��ꍇ
    mkdir ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}
  fi
  
  is_lbc_bach_start=${FALSE}
  input_csvfile_fullpath_array=`find ${INPUT_DIR_PATH}/${oneday_dir} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
  for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
    # �ړ�
    move_input_csv_file ${input_csvfile_fullpath} ${IMPORT_AFTER_DIR_PATH}/${MOVE_TODAY_DIR}/
    # �o�b�`�t���O
    if [ ${is_lbc_bach_start} = ${FALSE} ] ; then
      is_lbc_bach_start=${TRUE}
    fi
  done
  
  if [ ${is_lbc_bach_start} = ${TRUE} ] ; then
    lbc_bach_start
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
  
  TODAY_DIR_BACKUP=`date +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
  OLD_TODAY_DIR_BACKUP=`date -d "1 day ago" +'%Y%m%d'`${BACKUP_FILE_NAME_PATTERN}
  
  if [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} -a -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
    csv_file_two_dir $TODAY_DIR_BACKUP $OLD_TODAY_DIR_BACKUP
  elif [ -e ${INPUT_DIR_PATH}/${TODAY_DIR_BACKUP} ]; then
    csv_file_one_dir $TODAY_DIR_BACKUP
  elif [ -e ${INPUT_DIR_PATH}/${OLD_TODAY_DIR_BACKUP} ]; then
    csv_file_one_dir $OLD_TODAY_DIR_BACKUP
  else
      # ���݂��Ȃ��ꍇ
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
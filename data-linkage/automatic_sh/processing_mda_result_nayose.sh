#!/bin/bash
# processing_mda_result_nayose.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# �Ǎ��t�@�C���p�^�[����
INPUT_FILE_NAME_PATTERN1=${INPUT_FILE_NAME_PATTERN_MAPING_MULTIPLE}
INPUT_FILE_NAME_PATTERN2=${INPUT_FILE_NAME_PATTERN_MAPING_UNIQUE}

INPUT_TITLE_NAME_SIZE1=16
INPUT_TITLE_NAME_SIZE2=14

INPUT_TITLE_HIZUKE_SIZE=14

OUTPUT_FILE_NAME_PATTERN1=${OUTPUT_FILE_NAME_PATTERN_MAPING_MULTIPLE}
OUTPUT_FILE_NAME_PATTERN2=${OUTPUT_FILE_NAME_PATTERN_MAPING_UNIQUE}

# �Ď��f�B���N�g��
INPUT_DIR_PATH=${MAPING_RESPONSE_DIR_PATH}

# �N���L��
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# �N���L���p�^�[����
PREFIX_OF_FILENAME_ON_PROCESSING=${MDA_RESULT_NAYOSE_PROCESSING}
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

#�t�@�C�����R�s�[
function copy_gunzip_csv_file {
  my_echo "copy_gunzip_csv_file $1"
  input_file_path=$1
  
  # �t�@�C����
  input_csv_filename=`basename ${input_file_path}`
  if [ ! -e ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${input_csv_filename} ]; then
    # ���݂��Ȃ��ꍇ
    `cp ${input_file_path} ${NAYOSE_IMPORT_AFTER_DIR_PATH}/`
  else
    # ���ݏꍇ����ꍇ
    `cp -f ${input_file_path} ${NAYOSE_IMPORT_AFTER_DIR_PATH}/`
  fi
  # ��
  `gunzip -f ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${input_csv_filename}`
}

# �}�b�`���O�t�@�C�����ʂ��ړ�
function maching_csv_file {
  my_echo "process_csv_file_copy"
  if [ ! -e ${INPUT_DIR_PATH} ]; then
    # ���݂��Ȃ��ꍇ
    echo "no maching response directory. exit."
    end_time
  fi
  
  num_of_csv_files1=`find ${INPUT_DIR_PATH} -name "*.csv" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | wc -l`
  if [ ${num_of_csv_files1} = 0 ] ; then
    my_echo 'no *.csv files. exit.'
  else
    # CSV�t�@�C������
    input_csv_fullpath_array=`find ${INPUT_DIR_PATH} -name "*.csv" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f | sort`
    for input_csv_fullpath in $input_csv_fullpath_array; do
      if [ -e ${input_csv_fullpath} ]; then
        # ���k
        `sudo -i gzip -f ${input_csv_fullpath}`
        my_echo 'gzip '${input_csv_fullpath}
      fi
    done
  fi
  
  num_of_csv_files2=`find ${INPUT_DIR_PATH} -name "*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f -name "${INPUT_FILE_NAME_PATTERN1}*" | wc -l`
  if [ ${num_of_csv_files2} = 0 ] ; then
    echo "no *.csv.gz files. exit."
    end_time
  fi
  
  TODAY_DIR=`date +'%Y%m%d'`
  if [ ! -e ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${TODAY_DIR} ]; then
    # ���݂��Ȃ��ꍇ
    mkdir ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${TODAY_DIR}
  fi
  
  is_nayose_bach_start=${FALSE}
  # multiple���k�t�@�C������
  input_multiple_gz_fullpath_array=`find ${INPUT_DIR_PATH} -name "*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f -name "${INPUT_FILE_NAME_PATTERN1}*" | sort`
  for input_multiple_gz_fullpath in $input_multiple_gz_fullpath_array; do
    # multiple���k�t�@�C����
    input_multiple_gz_filename=`basename ${input_multiple_gz_fullpath}`
    # ���t��
    hizuke_name=${input_multiple_gz_filename:INPUT_TITLE_NAME_SIZE1:INPUT_TITLE_HIZUKE_SIZE}
    
    num_of_maching_csv_files2=`find ${INPUT_DIR_PATH} -name "*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f -name "${INPUT_FILE_NAME_PATTERN2}${hizuke_name}*" | wc -l`
    if [ ${num_of_maching_csv_files2} = 0 ] ; then
      echo "no maching csv files. exit."${INPUT_FILE_NAME_PATTERN2}${hizuke_name}".csv.gz"
      continue
    fi
    # �Ή�����unique���k�t�@�C������
    input_unique_gz_fullpath=`find ${INPUT_DIR_PATH} -name "*.gz" -not -path "${MAPING_RESPONSE_OLD_DIR_PATH}/*" -type f -name "${INPUT_FILE_NAME_PATTERN2}${hizuke_name}*" | sort | head -n 1`

    # ���k�t�@�C�����R�s�[�𓀈ړ�
    copy_gunzip_csv_file ${input_multiple_gz_fullpath}
    copy_gunzip_csv_file ${input_unique_gz_fullpath}
    # ���k�t�@�C�����o�b�N�A�b�v��
    move_input_csv_file_sudo ${input_multiple_gz_fullpath} ${MAPING_RESPONSE_OLD_DIR_PATH}/
    move_input_csv_file_sudo ${input_unique_gz_fullpath} ${MAPING_RESPONSE_OLD_DIR_PATH}/
    # CSV�t�@�C����
    input_multiple_gz_filename_not_gz=`basename ${input_multiple_gz_fullpath} .gz`
    input_unique_gz_filename_not_gz=`basename ${input_unique_gz_fullpath} .gz`
    # CSV�t�@�C����Ή��t�H���_�ֈړ�
    move_input_csv_file ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${input_multiple_gz_filename_not_gz} ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${TODAY_DIR}/${hizuke_name}${OUTPUT_FILE_NAME_PATTERN1}
    move_input_csv_file ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${input_unique_gz_filename_not_gz} ${NAYOSE_IMPORT_AFTER_DIR_PATH}/${TODAY_DIR}/${hizuke_name}${OUTPUT_FILE_NAME_PATTERN2}
    
    is_nayose_bach_start=${TRUE}
  done
  
  if [ ${is_nayose_bach_start} = ${TRUE} ] ; then
    maching_nayose_lbcsbn_batch_start
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

  maching_csv_file
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

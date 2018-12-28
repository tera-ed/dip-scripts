#!/bin/bash
# move_tabaitai.sh
cd `dirname $0`

# ----------------------------------

source ./utils.sh

# ----------------------------------

# config

# �Ǎ��t�@�C���p�^�[����
INPUT_FILE_NAME_PATTERN=${INPUT_FILE_NAME_PATTERN_TABAITAI}
# �Ď��f�B���N�g��
INPUT_DIR_PATH_ARRAY=("/home/teramgmt/yashima_work")
OUTPUT_DIR_PATH=${IMPORT_AFTER_DIR_PATH}

# �N���L��
GLOBAL_VAR_ON_PROCESSING=${FALSE}

# �N���L���p�^�[����
PREFIX_OF_FILENAME_ON_PROCESSING=${MOVE_TABAITAI_PROCESSING}
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

# �I������
function end_time {
  delete_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
  
  echo 'end_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  exit
}

# �J�n
function main {
  echo 'start_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  
  is_processing=${FALSE}
  if [ "$(ls ./${CREATING_PROCESSING}* 2>/dev/null)" = '' ] ; then
    is_processing=${TRUE}
  else
    echo "during startup creating_mda_request.sh. exit."
  fi
  
  if [ ${is_processing} = ${TRUE} ] ; then
    exit_if_on_processing
    create_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
    
    # �Ή�����ߋ��t�@�C������
    output_old_dir_path=${OUTPUT_DIR_PATH}/`date -d "1 day ago" +'%Y%m%d'`_11
    if [ -e ${output_old_dir_path} ]; then
      num_of_csv_files1=`find ${output_old_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN}" | wc -l`
      if [ ${num_of_csv_files1} > 0 ] ; then
        input_csvfile_fullpath_array=`find ${output_old_dir_path} -maxdepth 1 -regex ${INPUT_FILE_NAME_PATTERN} -type f | sort`
        
        if [ ! -e ${output_dir_path} ]; then
          # ���݂��Ȃ��ꍇ�A�f�B���N�g�����쐬
          mkdir ${output_dir_path}
        fi
        
        for input_csvfile_fullpath in $input_csvfile_fullpath_array; do
          # �ړ�
          move_input_csv_file ${input_csvfile_fullpath} ${output_dir_path}/
        done
      fi
    fi
    
    # �Ή�����t�@�C������
    output_dir_path=${OUTPUT_DIR_PATH}/`date +'%Y%m%d'`_11
    if [ -e ${output_dir_path} ]; then
      num_of_csv_files2=`find ${output_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN}" | wc -l`
      if [ ${num_of_csv_files2} > 0 ] ; then
        #�ړ���f�B���N�g���ɑΉ��t�@�C��������ꍇ
        echo "already csv files. exit."
        end_time
      fi
    fi
    
    for input_dir_path in ${INPUT_DIR_PATH_ARRAY[@]}; do
      if [ -e ${input_dir_path} ]; then
        # �Ή�����t�@�C������
        num_of_csv_files3=`find ${input_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN}" | wc -l`
        if [ ${num_of_csv_files3} = 0 ] ; then
          # �Ή��f�B���N�g���Ƀt�@�C�������݂��Ȃ�
          echo "${input_dir_path} : no new csv files. exit."
          continue
        fi
        
        if [ ! -e ${output_dir_path} ]; then
          # ���݂��Ȃ��ꍇ�A�f�B���N�g�����쐬
          mkdir ${output_dir_path}
        fi
        
        num_of_csv_files4=`find ${output_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN}" | wc -l`
        if [ ${num_of_csv_files4} = 0 ] ; then
          # �ړ���f�B���N�g���ɑΉ��t�@�C����1���Ȃ��ꍇ�ړ����s��
          input_csv_fullpath=`find ${input_dir_path} -name "*.csv" -type f -name "${INPUT_FILE_NAME_PATTERN}" | sort -rn | head -n 1`
          move_input_csv_file ${input_csv_fullpath} ${output_dir_path}/
        fi
        
        # 1�t�@�C���Ή��ŏI��
        break
      fi
    done
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

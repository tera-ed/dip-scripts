#!/bin/bash
# utils.sh
cd `dirname $0`

# ----------------------------------

source ./file_path_config.sh

# ----------------------------------
# config

# ���O�f�o�b�O�L��
#GLOBAL_VAR_DEBUG=${FALSE}
GLOBAL_VAR_DEBUG=${TRUE}

# ----------------------------------

# �f�o�b�O�p���O
function my_echo {
  if [ ${GLOBAL_VAR_DEBUG} = ${TRUE} ] ; then
    echo '['`date +'%Y/%m/%d %H:%M:%S.%N'`']'$@
  fi
}

# �N���L���`�F�b�N
function on_processing_file {
  my_echo "on_processing"
  if [ "$(ls ./${PREFIX_OF_FILENAME_ON_PROCESSING}* 2>/dev/null)" = '' ] ; then
    my_echo "on_processing false"
    GLOBAL_VAR_ON_PROCESSING=${FALSE}
  else
    my_echo "on_processing true"
    GLOBAL_VAR_ON_PROCESSING=${TRUE}
  fi
}

# �N���m�F�p�t�@�C���쐬
# �N�����ɉ��x���N�����s��Ȃ��悤�ɂ��邽��
function create_flagfile_about_processing {
  my_echo "create_flagfile_about_processing $1"
  flagfilename=$1
  touch ${flagfilename}
}

# �N��������ꍇ�͓���I��
function exit_if_on_processing {
  on_processing_file
  if [ ${GLOBAL_VAR_ON_PROCESSING} = ${TRUE} ] ; then
    echo "on processing. exit."
    end_time
  fi
}

# �N���m�F�t�@�C���폜
function delete_flagfile_about_processing {
  my_echo "delete_flagfile_about_processing $1"
  flagfilename=$1
  `rm ${SOURCE_DIR_PATH}/${flagfilename}`
}

# �O�̃G���[�t�@�C�����k
function zip_old_error_file {
  my_echo "zip_old_request_file"
  num_of_unziped_csv_files=`find ${ERROR_INPUT_DIR_PATH} -maxdepth 1 -type f -not -regex ${ZIPPED_FILE_NAME_PATTERN} | wc -l | sed -e 's/ //g'`

  if [ ${num_of_unziped_csv_files} = 0 ] ; then
    my_echo "no need to zip error.csv"
  else
    `find ${ERROR_INPUT_DIR_PATH} -maxdepth 1 -type f -not -regex ${ZIPPED_FILE_NAME_PATTERN} | xargs gzip -f`
  fi
}

#�G���[�t�@�C�����R�s�[
function copy_error_file {
  input_csv_file_path=$1
  copy_input_csv_file ${input_csv_file_path} ${ERROR_INPUT_DIR_PATH}/
}

# �t�@�C�����f�B���N�g���ֈړ�
function move_input_csv_file {
  my_echo "move_input_csv_file $1 to $2"
  input_csv_file_path=$1
  output_path=$2
  `mv -f ${input_csv_file_path} ${output_path}`
  echo 'moved '${input_csv_file_path}' to '${output_path}
}

# �t�@�C�����f�B���N�g���ֈړ�(root)
function move_input_csv_file_sudo {
  my_echo "move_input_csv_file_sudo $1 to $2"
  input_csv_file_path=$1
  output_path=$2
  `sudo -i mv -f ${input_csv_file_path} ${output_path}`
  echo 'moved '${input_csv_file_path}' to '${output_path}
}

# ----------------------------------

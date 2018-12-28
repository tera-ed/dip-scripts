#!/bin/bash
# utils.sh
cd `dirname $0`

# ----------------------------------

source ./file_path_config.sh

# ----------------------------------
# config

# ���O�f�o�b�O�L��
GLOBAL_VAR_DEBUG=${FALSE}
#GLOBAL_VAR_DEBUG=${TRUE}

# ----------------------------------

# �f�o�b�O�p���O
function my_echo {
  if [ ${GLOBAL_VAR_DEBUG} = ${TRUE} ] ; then
    echo '['`date +'%Y/%m/%d %H:%M:%S.%N'`']'$@
  fi
}

# �N���L���`�F�b�N
function on_processing_file {
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
  if [ ${GLOBAL_VAR_ON_PROCESSING} = ${FALSE} ] ; then
    my_echo "delete_flagfile_about_processing $1"
    flagfilepath=${SOURCE_DIR_PATH}/$1
    if [ -e ${flagfilepath} ]; then
      `rm -rf ${flagfilepath}`
    fi
  fi
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

# �}�b�`���O���ʖ��񂹓��� LBCSBN���` ������(P25)
function maching_nayose_lbcsbn_batch_start {
  my_echo "nayose_bach_start"
  
  bach_data=$(cd /home/teramgmt/temp/data-linkage-nayose/codes; /usr/bin/php nayose_batch_start.php 0,25,35)
  if [ -z ${bach_data} ] ; then
    echo "success nayose_batch_start.php"
  else
    echo '[ERROR] '$bach_data
  fi
}

# ���}�̎捞�̂� ������(P11)
function tabaitai_torikomi_batch_start {
  my_echo "lbc_bach_start"
  bach_data=$(cd /home/teramgmt/temp/data-linkage-nayose/codes; /usr/bin/php lbc_batch_start.php 0,11,14,15,18,19)
  if [ -z ${bach_data} ] ; then
    echo "success lbc_batch_start.php"
  else
    echo '[ERROR] '$bach_data
  fi
}

# ������}�b�`���O���ʎ捞 ������(P9)
function lbc_maching_batch_start {
  my_echo "lbc_bach_start"
  bach_data=$(cd /home/teramgmt/temp/data-linkage-nayose/codes; /usr/bin/php lbc_batch_start.php 0,9,20)
  if [ -z ${bach_data} ] ; then
    echo "success lbc_batch_start.php"
  else
    echo '[ERROR] '$bach_data
  fi
}

# ----------------------------------

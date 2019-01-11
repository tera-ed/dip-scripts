#!/bin/bash
# utils.sh
cd `dirname $0`

# ----------------------------------

source ./file_path_config.sh

# ----------------------------------
# config

# ログデバッグ有無
#GLOBAL_VAR_DEBUG=${FALSE}
GLOBAL_VAR_DEBUG=${TRUE}

# ----------------------------------

# tey-catch Error
trap catch ERR

# エラー出力
function catch {
    echo CATCH
    end_time
}

# 終了動作
function end_time {
  delete_flagfile_about_processing ${FILENAME_ABOUT_PROCESSING}
  
  echo 'end_time '`date "+%Y/%m/%d %H:%M:%S.%N"`
  exit
}

# ----------------------------------

# デバッグ用ログ
function my_echo {
  if [ ${GLOBAL_VAR_DEBUG} = ${TRUE} ] ; then
    echo '['`date +'%Y/%m/%d %H:%M:%S.%N'`']'$@
  fi
}

# 起動有無チェック
function on_processing_file {
  if [ "$(ls ./${PREFIX_OF_FILENAME_ON_PROCESSING}* 2>/dev/null)" = '' ] ; then
    my_echo "on_processing false"
    GLOBAL_VAR_ON_PROCESSING=${FALSE}
  else
    my_echo "on_processing true"
    GLOBAL_VAR_ON_PROCESSING=${TRUE}
  fi
}

# 起動確認用ファイル作成
# 起動中に何度も起動を行わないようにするため
function create_flagfile_about_processing {
  my_echo "create_flagfile_about_processing $1"
  flagfilename=$1
  touch ${flagfilename}
}

# 起動がある場合は動作終了
function exit_if_on_processing {
  on_processing_file
  if [ ${GLOBAL_VAR_ON_PROCESSING} = ${TRUE} ] ; then
    echo "on processing. exit."
    end_time
  fi
}

# 起動確認ファイル削除
function delete_flagfile_about_processing {
  if [ ${GLOBAL_VAR_ON_PROCESSING} = ${FALSE} ] ; then
    my_echo "delete_flagfile_about_processing $1"
    processing_name=$1
    
    remove_file ${SOURCE_DIR_PATH}/${processing_name}
  fi
}

# ファイルをディレクトリへ移動
function move_input_csv_file {
  my_echo "move_input_csv_file $1 to $2"
  input_csv_file_path=$1
  output_path=$2
  `mv -f ${input_csv_file_path} ${output_path}`
  echo 'moved '${input_csv_file_path}' to '${output_path}
}

# ファイルをディレクトリへ移動(root)
function move_input_csv_file_sudo {
  my_echo "move_input_csv_file_sudo $1 to $2"
  input_csv_file_path=$1
  output_path=$2
  `sudo -i mv -f ${input_csv_file_path} ${output_path}`
  echo 'moved '${input_csv_file_path}' to '${output_path}
}

# ディレクトリを削除
function remove_dir {
  my_echo "remove_dir $1"
  input_dir_fullpath=$1
  
  if [ -e ${input_dir_fullpath} ]; then
    num_of_csv_files=`find ${input_dir_fullpath} -type f | wc -l`
    if [ ${num_of_csv_files} = 0 ] ; then
      `rm -rf ${input_dir_fullpath}`
    fi
  fi
}

# ファイルを削除
function remove_file {
  my_echo "remove_file $1"
  input_file_fullpath=$1
  if [ -e ${input_file_fullpath} ]; then
    `rm -rf ${input_file_fullpath}`
  fi
}

# ----------------------------------

# マッチング結果名寄せ統合 LBCSBN整形 自動化(P25)
function maching_nayose_lbcsbn_batch_start {
  my_echo "nayose_bach_start"
  
  bach_data=$(cd ${PHP_DIR_PATH}; /usr/bin/php nayose_batch_start.php 0,25,35)
  if [ -z ${bach_data} ] ; then
    echo "success nayose_batch_start.php"
  else
    echo '[ERROR] '$bach_data
  fi
}

# 他媒体取込のみ 自動化(P11)
function tabaitai_torikomi_batch_start {
  my_echo "lbc_bach_start"
  bach_data=$(cd ${PHP_DIR_PATH}; /usr/bin/php lbc_batch_start.php 0,11,14,15,18,19)
  if [ -z ${bach_data} ] ; then
    echo "success lbc_batch_start.php"
  else
    echo '[ERROR] '$bach_data
  fi
}

# 次世代マッチング結果取込 自動化(P9)
function lbc_maching_batch_start {
  my_echo "lbc_bach_start"
  bach_data=$(cd ${PHP_DIR_PATH}; /usr/bin/php lbc_batch_start.php 0,9,20)
  if [ -z ${bach_data} ] ; then
    echo "success lbc_batch_start.php"
  else
    echo '[ERROR] '$bach_data
  fi
}

# ----------------------------------

#################################################
#! /bin/bash
#
# ファイル名：file_path_config.sh
#
#################################################
export LANG=ja_JP.UTF-8

BACKUP_FILE_NAME_PATTERN="_comp"

INPUT_FILE_NAME_PATTERN_MDA_REQ=".*/[0-9]+_MDA_Request\.csv"
INPUT_FILE_NAME_PATTERN_MDA_RES=".*/[0-9]+_MDA_Result\.csv"
INPUT_FILE_NAME_PATTERN_FORCE_RES=".*/[0-9]+_FORCE_Result\.csv"
INPUT_FILE_NAME_PATTERN_TABAITAI="TABAITAI__*.csv"
INPUT_FILE_NAME_PATTERN_FORCE="FORCE__*.csv"
INPUT_FILE_NAME_PATTERN_MAPING_MULTIPLE="Result_Multiple_"
INPUT_FILE_NAME_PATTERN_MAPING_UNIQUE="Result_Unique_"

# ----------------------------------

OUTPUT_FILE_NAME_PATTERN_MAPING_MULTIPLE="_MDA_Result_Multiple.csv"
OUTPUT_FILE_NAME_PATTERN_MAPING_UNIQUE="_MDA_Result_Unique.csv"

# ----------------------------------

ZIPPED_FILE_NAME_PATTERN=".*/.*\.gz"
LOGFILE_SUFFIX=".log"
ERROR_DIR_NAME="error"

# ----------------------------------

FALSE=0
TRUE=1

# ----------------------------------

SOURCE_DIR_PATH="/home/teramgmt/temp/data-linkage-nayose/automatic_sh"
ERROR_INPUT_DIR_PATH=${SOURCE_DIR_PATH}/"error"
LOG_INPUT_DIR_PATH=${SOURCE_DIR_PATH}"/log"

PHP_DIR_PATH="/home/teramgmt/temp/data-linkage-nayose/codes"
PHP_TMP_DIR_PATH=${PHP_DIR_PATH}"/tmp"
EXPORT_AFTER_DIR_PATH=${PHP_TMP_DIR_PATH}"/csv/Export/after"
EXPORT_BEFORE_DIR_PATH=${PHP_TMP_DIR_PATH}"/csv/Export/before"
IMPORT_AFTER_DIR_PATH=${PHP_TMP_DIR_PATH}"/csv/Import/after"
NAYOSE_IMPORT_AFTER_DIR_PATH=${PHP_TMP_DIR_PATH}"/nayose_csv/Import/after"
NAYOSE_EXPORT_BEFORE_DIR_PATH=${PHP_TMP_DIR_PATH}"/nayose_csv/Export/before"

MAPING_REQUEST_DIR_PATH="/var/csv/media_request"
MAPING_RESPONSE_DIR_PATH="/var/csv/media_response"
MAPING_RESPONSE_OLD_DIR_PATH=${MAPING_RESPONSE_DIR_PATH}"/old"


PYTHON_USER="dip-sysinfo"

PYTHON_SOURCE_DIR_PATH="/home/dip-sysinfo/crm_src_cfl"
PYTHON_SOURCE_INPUT_PATH=${PYTHON_SOURCE_DIR_PATH}"/files/input/FairRivalMedia/p2_you"
PYTHON_SOURCE_OUTPUT_PATH=${PYTHON_SOURCE_DIR_PATH}"/files/output/RivalMedia/python2"
PYTHON_SOURCE_OK_PATH=${PYTHON_SOURCE_OUTPUT_PATH}"/OK"
PYTHON_SOURCE_DONE_PATH=${PYTHON_SOURCE_OK_PATH}"/Done"
PYTHON_SOURCE_SOURCE="run-shell.sh"


FILE_TRANSACTER_DIR_PATH="/home/dip-sysinfo/CRM_File_Transacter"
FILE_TRANSACTER_SOURCE="run-shell.sh"

FILE_TABAITAI_UPLOAD_PATH="/home/dip-sysinfo/CRM_Tabaitai_Upload"
FILE_TABAITAI_UPLOAD_SOURCE="run-shell.sh"

TABAITAI_DIR_PATHS=("/home/teramgmt/yashima_work/01_priority" "/home/teramgmt/yashima_work/02_non_priority")


# ----------------------------------

PROCESSING1="CREATING_MDA_REQUEST_PROCESSING"
PROCESSING2="MDA_RESULT_IMPORT_PROCESSING"
PROCESSING3="MDA_RESULT_NAYOSE_PROCESSING"
PROCESSING4="SENDING_REQUEST_TO_MATCHING_PROCESSING"
PROCESSING5="MOVE_TABAITAI_PROCESSING"
PROCESSING6="CRM_FILE_TRANSACTER_PROCESSING"
PROCESSING7="CRM_SRC_CFL_PROCESSING"
PROCESSING8="GET_TABAITAI_PROCESSING"

# ----------------------------------

#!/bin/bash
# file_path_config.sh
cd `dirname $0`

# ----------------------------------

BACKUP_FILE_NAME_PATTERN="_comp"

INPUT_FILE_NAME_PATTERN_MDA_REQ=".*/[0-9]+_MDA_Request\.csv"
INPUT_FILE_NAME_PATTERN_MDA_RES=".*/[0-9]+_MDA_Result\.csv"
INPUT_FILE_NAME_PATTERN_TABAITAI="TABAITAI__*.csv"
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

SOURCE_DIR_PATH=/home/teramgmt/temp/data-linkage-nayose/automatic_sh
ERROR_INPUT_DIR_PATH=${SOURCE_DIR_PATH}/error
LOG_INPUT_DIR_PATH=${SOURCE_DIR_PATH}/log

PHP_TMP_DIR_PATH=/home/teramgmt/temp/data-linkage-nayose/codes/tmp
EXPORT_AFTER_DIR_PATH=${PHP_TMP_DIR_PATH}/csv/Export/after
IMPORT_AFTER_DIR_PATH=${PHP_TMP_DIR_PATH}/csv/Import/after
NAYOSE_IMPORT_AFTER_DIR_PATH=${PHP_TMP_DIR_PATH}/nayose_csv/Import/after
NAYOSE_EXPORT_BEFORE_DIR_PATH=${PHP_TMP_DIR_PATH}/nayose_csv/Export/before

MAPING_REQUEST_DIR_PATH=/var/csv/media_request
MAPING_RESPONSE_DIR_PATH=/var/csv/media_response
MAPING_RESPONSE_OLD_DIR_PATH=${MAPING_RESPONSE_DIR_PATH}/old

# ----------------------------------

CREATING_PROCESSING="CREATING_PROCESSING"
MDA_RESULT_INPORT_PROCESSING="MDA_RESULT_INPORT_PROCESSING"
MDA_RESULT_NAYOSE_PROCESSING="MDA_RESULT_NAYOSE_PROCESSING"
SENDING_REQUWET_PROCESSING="SENDING_REQUWET_PROCESSING"

# ----------------------------------

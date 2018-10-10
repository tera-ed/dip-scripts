#!/bin/bash

TODAY=$(date +%Y%m%d)
TODAY_YM=$(date +%Y%m)
TODAY_UTF8="${TODAY}_utf8"

YESTD=$(date --date "TODAY 1 days ago" +%Y%m%d)
YESTD_YM=$(date +%Y%m)

FILE_OUT_PATH="/home/teramgmt/python-scripts/out"
FILE_COPY_PATH="/home/teramgmt/share/RivalMedia/FileHere/summary_nego-media"
LOG_PATH="/home/teramgmt/python-scripts/log/media_summary.log"

set -e
trap 'onerror "ERROR"' ERR

onerror(){
    printEcho "$1" 
}
printEcho () {
    echo "$1" >> $LOG_PATH
}

printEcho "========================================================="
printEcho "[sh_meida_summary.sh]が開始されました。指定日：$1"

YESTD_UTF8="${TODAY}_utf8"

echo "media_summary_${TODAY_YM}_${TODAY}.csv"
echo "media_summary_${YESTD_YM}_${YESTD}.csv"

#昨日のファイルは圧縮してbkへ
if [ -e $FILE_OUT_PATH/media_summary_${YESTD_YM}_${YESTD}.csv ]; then
    printEcho "$YESTD日付のファイルは圧縮しbkフォルダへ移動します。"
    gzip $FILE_OUT_PATH/media_summary_${YESTD_YM}_${YESTD}.csv
    gzip $FILE_OUT_PATH/media_summary_${YESTD_YM}_${YESTD}_utf8.csv
    mv -f $FILE_OUT_PATH/media_summary_${YESTD_YM}_${YESTD}.csv.gz $FILE_OUT_PATH/bk/.
    mv -f $FILE_OUT_PATH/media_summary_${YESTD_YM}_${YESTD}_utf8.csv.gz $FILE_OUT_PATH/bk/.
fi

printEcho "サマリー処理を開始します。"
/home/teramgmt/.pyenv/shims/python batch_t_media_summary_update.py

printEcho "CSV出力処理を開始します。"
/home/teramgmt/.pyenv/shims/python batch_t_media_summary_output_csv.py

printEcho "ファイルを送付します。送付先：$FILE_COPY_PATH"
cp $FILE_OUT_PATH/media_summary_${TODAY_YM}_${TODAY}.csv $FILE_COPY_PATH/.
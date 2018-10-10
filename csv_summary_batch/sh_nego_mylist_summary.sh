#!/bin/bash

exec_date=$1
YESTD=$(date --date "$1 1 days ago" +%Y%m%d)
YESTD_UTF8="${YESTD}_utf8"
EXEX_OUT_PATH="/home/teramgmt/.pyenv/shims"
FILE_OUT_PATH="/home/teramgmt/python-scripts/out"
FILE_COPY_PATH="/home/teramgmt/share/RivalMedia/FileHere/summary_nego-media"
LOG_PATH="/home/teramgmt/python-scripts/log/nego_mylist_summary.log"

set -e
trap 'onerror "ERROR"' ERR

onerror(){
    printEcho "$1" 
}
printEcho () {
    echo "$1" >> $LOG_PATH
}

printEcho "========================================================="
printEcho "[sh_nego_mylist_summary.sh]が開始されました。指定日：$1"

#昨日のファイルは圧縮してbkへ
if [ -e $FILE_OUT_PATH/nego_summary_$YESTD.csv ]; then
    printEcho "$YESTD日付のファイルは圧縮しbkフォルダへ移動します。"
    gzip $FILE_OUT_PATH/nego_summary_$YESTD.csv
    gzip $FILE_OUT_PATH/nego_summary_$YESTD_UTF8.csv
    mv -f $FILE_OUT_PATH/nego_summary_$YESTD.csv.gz $FILE_OUT_PATH/bk/.
    mv -f $FILE_OUT_PATH/nego_summary_$YESTD_UTF8.csv.gz $FILE_OUT_PATH/bk/.
fi

printEcho "サマリー処理を開始します。"
$EXEX_OUT_PATH/python batch_t_nego_mylist_insert.py $1
$EXEX_OUT_PATH/python batch_t_nego_mylist_summary_insert.py $1

printEcho "CSV出力処理を開始します。" 
$EXEX_OUT_PATH/python batch_t_nego_mylist_output_csv.py $1

printEcho "ファイルを送付します。送付先：$FILE_COPY_PATH"
cp $FILE_OUT_PATH/nego_summary_$1.csv $FILE_COPY_PATH/.

printEcho "[sh_nego_mylist_summary.sh]が終了しました。"
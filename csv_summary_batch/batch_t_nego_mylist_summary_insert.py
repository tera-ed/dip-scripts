#!/usr/bin/env python
# coding:UTF-8

# --------------------------
#  batch_t_nego_mylist_summary_insert 
#  商談サマリテーブルを作成する
# --------------------------
from common import mylog as logger
from datetime import *

import configparser
import pymysql

import sys # モジュール属性 argv を取得するため
argvs = sys.argv[1]  # コマンドライン引数を格納したリストの取得

config = configparser.ConfigParser()
config.read('config.ini')

db_params1 = {
    'database': config['MYSQL']['database'],
    'user': config['MYSQL']['user'],
    'password': config['MYSQL']['password'],
    'host' : config['MYSQL']['host'],
    'port': int(config['MYSQL']['port']),
    'charset' : config['MYSQL']['charset']
}

# --------------------------
#  insNegoMylistSummery 
#  集計対象顧客コード・営業コード・日ごとに商談を集計して登録
# --------------------------
def insNegoMylistSummery():

    exec_date = datetime.strptime(argvs, '%Y%m%d')
    end_date = exec_date + timedelta(days=1)
    start_date =  exec_date - timedelta(days=31)

    param_list = []
    param_list.append(start_date)
    param_list.append(end_date)

    logger.debug('集計期間'+start_date.strftime('%Y-%m-%d')+'から'+exec_date.strftime('%Y-%m-%d'))

    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        #一時保存はカウントしない　自由メモはカウントしない　顧客コードがないものはカウントしない
        sql = """
                INSERT INTO t_nego_mylist_summary (corporation_code,member_code,summery_date,count)
                select tn.corporation_code,tn.member_code,DATE_FORMAT(tn.record_date, '%%Y-%%m-%%d') as rec_date,count(0) as cnt
                from t_negotiation tn inner join t_nego_mylist tnm on tn.corporation_code=tnm.corporation_code and tn.member_code = tnm.member_code where tn.record_date>=%s and tn.record_date<%s and tn.nego_state_kbn = '0' and contact_code <> '5' and tn.corporation_code is not null
                group by tn.corporation_code,tn.member_code,DATE_FORMAT(tn.record_date, '%%Y-%%m-%%d')
                ;
                """
        cursor.execute(sql.format(),(param_list[0],param_list[1]))
        conn.commit()
        

# --------------------------
#  delNegoMylistSummery 
#  全件削除
# --------------------------
def delNegoMylistSummery():
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        cursor.execute("""
        truncate table t_nego_mylist_summary;
        """ )
        conn.commit()
        
# --------------------------
#  loggingTimeString 
#  ログ出力関数
# --------------------------  
def loggingTimeString():
    now = datetime.now()
    tstr = now.strftime('%Y-%m-%d %H:%M:%S')
    return tstr

# --------------------------
#  main 
#  メイン
# -------------------------- 
def main():

    logger.debug('[batch_t_nego_mylist_summary_insert]を開始します。指定された日＝'+argvs)

    #トランケートする
    logger.debug('delNegoMylist start:'+loggingTimeString())
    delNegoMylistSummery()
    logger.debug('delNegoMylist End:'+loggingTimeString())

    #データ取得後INSERTする
    logger.debug('insNegoMylist start:'+loggingTimeString())
    insNegoMylistSummery()
    logger.debug('insNegoMylist End:'+loggingTimeString())
    
    logger.debug('[batch_t_nego_mylist_summary_insert]を終了します。')

if __name__ == '__main__':
    main()

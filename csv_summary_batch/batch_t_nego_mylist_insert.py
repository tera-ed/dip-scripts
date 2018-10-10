#!/usr/bin/env python
# coding:UTF-8

# --------------------------
#  batch_t_nego_mylist_insert.py
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
#  insNegoMylist 
#  集計対象顧客コードを取得する
#  パラメータで指定された日付より1か月前に商談があった顧客・担当を取得する
# --------------------------
def insNegoMylist():

    #パラメータより集計日を決定
    exec_date = datetime.strptime(argvs, '%Y%m%d')
    end_date = exec_date + timedelta(days=1)
    start_date =  exec_date - timedelta(days=31)

    param_list = []
    param_list.append(start_date)
    param_list.append(end_date)

    logger.debug('集計期間'+start_date.strftime('%Y-%m-%d')+'から'+exec_date.strftime('%Y-%m-%d'))
    
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        sql = """
                INSERT INTO t_nego_mylist (corporation_code,member_code,business_priority,segment,visible_flag,incharge_flag,recolin_flag)
                select tn.corporation_code,	
                tn.member_code,	
                mc.business_priority,	
                mc.free_item2,	
                CASE WHEN mslv.member_code is NULL THEN 0 WHEN mslv.member_code is NOT NULL THEN 1 ELSE 9 END as visible_flag,	
                CASE WHEN msl.member_code is NULL THEN 0 WHEN msl.member_code is NOT NULL THEN 1 ELSE 9 END as incharge_flag,	
                1 as recolin_flag	
                from (select * from t_negotiation where record_date>=%s and record_date<%s and nego_state_kbn = '0' and corporation_code is not null group by corporation_code,member_code) tn 	
                    inner join m_corporation mc on tn.corporation_code=mc.corporation_code
                    inner join m_member mm on tn.member_code=mm.member_code
                    left outer join m_sales_link_visible mslv on tn.member_code=mslv.member_code and tn.corporation_code=mslv.corporation_code and mslv.delete_flag = true and corp_hide_code = '4'
                    left outer join m_sales_link msl on tn.member_code=msl.member_code and tn.corporation_code=msl.corporation_code and msl.delete_flag = false
                group by tn.corporation_code,tn.member_code
                ;
                """
        cursor.execute(sql,(param_list[0],param_list[1]))
        conn.commit()
        

# --------------------------
#  delNegoMylist 
#  t_nego_mylistテーブル全件削除
# --------------------------
def delNegoMylist():
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        cursor.execute("""
        truncate table t_nego_mylist;
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

    logger.debug('======================================================')
    logger.debug('[batch_t_nego_mylist_insert]を開始します。指定された日＝'+argvs)

    #トランケートする
    logger.debug('delNegoMylist start:'+loggingTimeString())
    delNegoMylist()
    logger.debug('delNegoMylist End:'+loggingTimeString())

    #データ取得後INSERTする
    logger.debug('insNegoMylist start:'+loggingTimeString())
    insNegoMylist()
    logger.debug('insNegoMylist End:'+loggingTimeString())

    logger.debug('[batch_t_nego_mylist_insert]を終了します。')

if __name__ == '__main__':
    main()

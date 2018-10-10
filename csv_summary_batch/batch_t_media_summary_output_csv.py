#!/usr/bin/env python
# coding:UTF-8

# --------------------------
#  batch_t_media_summary_update 
#  他媒体集計テーブルを更新する
# --------------------------
from common import mylog as logger
import configparser
import pandas as pd
import numpy as np
import subprocess
import pymysql
import csv
import datetime


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
#  getSummaryDatas 
#  当月分の集計データを取得する
# --------------------------
def getSummaryDatas():
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        cursor.execute("""
        SELECT 
        corporation_code,
        compe_media_code,
        (select corporation_name from m_corporation as mc where mc.corporation_code = t1.corporation_code) AS corporation_name,
        (select compe_media_name from m_media_mass as mm where mm.compe_media_code = t1.compe_media_code) AS compe_media_name,
        MAX(CASE WHEN week_no = WEEK(DATE_FORMAT(CURRENT_DATE, '%Y%m01'), 3)  THEN count ELSE 0 END) as w1 ,
        MAX(CASE WHEN week_no = WEEK(DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL +1 WEEK), 3)  THEN count ELSE 0 END) as w2,
        MAX(CASE WHEN week_no = WEEK(DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL +2 WEEK), 3)  THEN count ELSE 0 END) as w3,
        MAX(CASE WHEN week_no = WEEK(DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL +3 WEEK), 3)  THEN count ELSE 0 END) as w4,
        MAX(CASE WHEN week_no = WEEK(DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL +4 WEEK), 3)  THEN count ELSE 0 END) as w5
        FROM 
        (
        SELECT 
            corporation_code, 
            compe_media_code, 
            WEEK(post_end_date, 3) AS week_no, 
            SUM(count) AS count 
        FROM 
            t_weekly_summary_media_mass
        WHERE 
            WEEK(post_end_date, 3) >= WEEK(DATE_FORMAT(CURRENT_DATE, '%Y%m01'), 3)
        AND post_end_date >= DATE_ADD(NOW(), INTERVAL -2 MONTH)
            /* AND DATE_FORMAT(post_end_date, '%Y%m') = DATE_FORMAT(CURRENT_DATE, '%Y%m') */
            GROUP BY corporation_code, compe_media_code,WEEK(post_end_date, 3)
        ) AS t1
        GROUP BY 
        corporation_code,compe_media_code
        ;
        """)
        return (np.array(cursor.fetchall()))

# --------------------------
#  getHeaderDate 
#  集計期間表示用の日付を取得する。
#　| w1         | w2         | w3         | w4         | w5         |
#　+------------+------------+------------+------------+------------+
#　| 2018-08-27 | 2018-09-03 | 2018-09-10 | 2018-09-17 | 2018-09-24 |
#　| 2018-09-02 | 2018-09-09 | 2018-09-16 | 2018-09-23 | 2018-09-30 |
# --------------------------
def getHeaderDate():
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        cursor.execute("""
        SELECT 
            w1 - INTERVAL WEEKDAY(w1) DAY AS w1,
            w2 - INTERVAL WEEKDAY(w2) DAY AS w2,
            w3 - INTERVAL WEEKDAY(w3) DAY AS w3,
            w4 - INTERVAL WEEKDAY(w4) DAY AS w4,
            w5 - INTERVAL WEEKDAY(w5) DAY AS w5
        FROM (
        SELECT
            DATE_FORMAT(CURRENT_DATE, '%Y%m01') AS w1,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 1 WEEK)  AS w2,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 2 WEEK)  AS w3,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 3 WEEK)  AS w4,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 4 WEEK)  AS w5
        ) AS w
        UNION
        SELECT 
            DATE_ADD(w1 - INTERVAL WEEKDAY(w1) DAY, INTERVAL 6 DAY) AS w1,
            DATE_ADD(w2 - INTERVAL WEEKDAY(w2) DAY, INTERVAL 6 DAY) AS w2,
            DATE_ADD(w3 - INTERVAL WEEKDAY(w3) DAY, INTERVAL 6 DAY) AS w3,
            DATE_ADD(w4 - INTERVAL WEEKDAY(w4) DAY, INTERVAL 6 DAY) AS w4,
            DATE_ADD(w5 - INTERVAL WEEKDAY(w5) DAY, INTERVAL 6 DAY) AS w5
        FROM (
            SELECT
            DATE_FORMAT(CURRENT_DATE, '%Y%m01') AS w1,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 1 WEEK)  AS w2,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 2 WEEK)  AS w3,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 3 WEEK)  AS w4,
            DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y%m01') , INTERVAL 4 WEEK)  AS w5
            ) AS w; 
        """)
        return cursor.fetchall() 

def outputCsv(hader_dates,summary_datas):

    to_year_month =datetime.date.today().strftime("%Y%m")
    to_date  = datetime.date.today().strftime("%Y%m%d")
    out_file_path = config['DEFAULT']['csv_out_path'] + "media_summary_" + str(to_year_month) + "_" + str(to_date)
    out_file_utf8 = out_file_path + "_utf8.csv"
    out_file_sjis = out_file_path + ".csv"
    header = ["顧客コード","競合媒体コード","顧客名","競合媒体名"]
    header.append( hader_dates[0][0] + "~" + hader_dates[1][0] )
    header.append( hader_dates[0][1] + "~" + hader_dates[1][1] )
    header.append( hader_dates[0][2] + "~" + hader_dates[1][2] )
    header.append( hader_dates[0][3] + "~" + hader_dates[1][3] )
    header.append( hader_dates[0][4] + "~" + hader_dates[1][4] )
    if summary_datas.shape[0] > 0 :
        df = pd.DataFrame(summary_datas, columns=header)
        df.to_csv(out_file_utf8, index=False, )
    else :
        # 空のCSVを出力する
        with open(out_file_utf8, 'w') as f:
            writer = csv.writer(f, lineterminator='\n')
            writer.writerow(header) 
    cmd = "nkf -s %s > %s" % (out_file_utf8, out_file_sjis)
    subprocess.call(cmd, shell=True)
            
def main():
    hader_dates   = getHeaderDate()
    summary_datas = getSummaryDatas()
    outputCsv(hader_dates,summary_datas)

if __name__ == '__main__':
    main()

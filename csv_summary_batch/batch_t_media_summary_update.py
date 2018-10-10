#!/usr/bin/env python
# coding:UTF-8

# --------------------------
#  batch_t_media_summary_update 
#  他媒体集計テーブルを更新する
# --------------------------
from common import mylog as logger
import configparser
import pymysql


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
#  getTargetDatas 
#  集計対象顧客コードを取得する
#  最終集計日(MAX(update_date))より後に更新された顧客コードを取得
# --------------------------
def getTargetDatas():
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        cursor.execute("""
        SELECT 
        corporation_code
        FROM 
        t_media_posted_corporation 
        WHERE last_update_date > (SELECT max(update_date) FROM t_weekly_summary_media_mass) 
        GROUP BY corporation_code
        /* LIMIT 50000 */
        """)
        return cursor.fetchall()

# --------------------------
#  getSummaryDatas 
#  対象顧客の2ヶ月分の集計データを再作成する
# count(0) AS count ⇒
# --------------------------
def getSummaryDatas(corporation_code):
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        sql = """
        SELECT 
        corporation_code,
        compe_media_code,
        post_end_date,
        sum(post_count) AS count
        FROM t_media_mass
        WHERE
        corporation_code = %s
        AND post_end_date >= DATE_ADD(NOW(), INTERVAL -2 MONTH)
        GROUP BY corporation_code,compe_media_code,post_end_date;
        """
        cursor.execute(sql,str(corporation_code))
        return cursor.fetchall()

# --------------------------
#  upsertData 
#  集計データのupsertを行う
# --------------------------
def upsertData(datas):
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        counter = 1
        for rec in datas:

            sql1 = """
            DELETE
            FROM t_weekly_summary_media_mass
            WHERE corporation_code = %s
            AND   compe_media_code = %s
            AND   post_end_date = %s
            """
            # .strftime('%Y-%m-%d %H:%M:%S')
            cursor.execute(sql1,(rec[0],rec[1],rec[2]))

            sql3 = """
            INSERT INTO t_weekly_summary_media_mass
            VALUES (%s,%s,%s,%s,now())
            """

            cursor.execute(sql3,(rec[0],rec[1],rec[2],rec[3]))
            conn.commit()
        conn.commit()

def main():
    try:
        target_datas = getTargetDatas()
        for data in target_datas:
            summary_datas = getSummaryDatas(data[0])
            upsertData(summary_datas)
    
    except Exception as e:
        logger.debug('Exeception occured:{}'+format(e))

if __name__ == '__main__':
    main()

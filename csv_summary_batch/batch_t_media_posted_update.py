#!/usr/bin/env python
# coding:UTF-8

# --------------------------
#  bach_t_media_posted_update 
#  他媒体掲載顧客テーブル更新用
#  顧客毎の最終更新日を更新する
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


def getDatas():
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        cursor.execute("""
        SELECT
        corporation_code,
        MAX(update_date) AS update_date
        FROM t_media_mass
        WHERE update_date > (SELECT max(last_update_date) FROM t_media_posted_corporation)
        GROUP BY corporation_code
        ORDER BY update_date
        /* LIMIT 50000 */
        """)
        return cursor.fetchall()

def upsertData(datas):
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        counter = 1
        for rec in datas:

            sql1 = """
            SELECT corporation_code
            FROM t_media_posted_corporation
            WHERE corporation_code = %s
            """

            cursor.execute(sql1,rec[0])
            search_code_num = len(list(cursor))

            if search_code_num == 1:
                sql2 = """
                UPDATE t_media_posted_corporation
                SET last_update_date = %s
                WHERE corporation_code = %s
                """
                cursor.execute(sql2,(rec[1],rec[0]))
            else:
                sql3 = """
                INSERT INTO t_media_posted_corporation
                VALUES (%s,%s)
                """
                cursor.execute(sql3,(rec[0],rec[1]))


            if counter % 2000 == 0:
                conn.commit()
            counter += 1
        conn.commit()

def main():
    update_datas = getDatas()
    upsertData(update_datas)
    

if __name__ == '__main__':
    main()

#!/usr/bin/env python
# coding:UTF-8
from common import mylog as logger
from datetime import *
import configparser
import pandas as pd
import numpy as np
import subprocess


import configparser
import pymysql
import csv
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
#  makeDateList 
#  出力対象日を計算して返す
#  Param なし
#  return date_list:日付型のリスト
# --------------------------   
def makeDateList():

    #SQL用日付配列 日付型で格納
    date_list=[]
    #パラメータの日が最後の日になる
    end_date= datetime.strptime(argvs, '%Y%m%d')
    #31日前を取得し、初回の日としてリストに追加
    start_date =  end_date - timedelta(days=31)
    date_list.append(start_date)

    #初回の日から31日分、日のデータを作成する
    current_date=start_date
    for i in range(0, 31):
        current_date = current_date + timedelta(days=1)
        date_list.append(current_date)
    
    return date_list

# --------------------------
#  makeHeaderLine 
#  出力対象日を計算して返す
#  Param prm_date_list：出力対象の日付をもつリスト
#  return header_datelist:ヘッダー部を格納するリスト
# --------------------------   
def makeHeaderLine(prm_date_list):

    #固定値　17項目
    header_datelist=['重点顧客フラグ','セグメント','レコリンフラグ','担当フラグ','レコリン表示除外フラグ','担当者コード','担当者姓名','部署名','顧客コード','顧客名称','顧客住所','顧客TEL','業種名称1','業種名称2','業種名称3','商談合計']

    #初回の日から31日分、日のデータを作成する
    for i in range(0, 32):
        header_datelist.append(prm_date_list[i].strftime('%Y-%m-%d'))
    
    return header_datelist

# --------------------------
#  getCorpAndInchargedata 
#  t_nego_mylistに紐つく情報を取得　CSVの縦軸になる
#  Param なし
#  return cursor.fetchall():SQL結果の配列
# --------------------------   
def getCorpAndInchargedata():
    
    conn = pymysql.connect(**db_params1)
    with conn.cursor() as cursor:
        sql = """
                select 
                tnm.business_priority,
                tnm.segment,
                tnm.recolin_flag,
                tnm.incharge_flag,
                tnm.visible_flag,
                tnm.member_code,
                sub.member_name,
                CONCAT(sub.bu,' ',sub.ka),
                tnm.corporation_code,
                mm.corporation_name,
                mm.addressall,
                mm.tel,
                mm.industry_name1,
                mm.industry_name2,
                mm.industry_name3
                from t_nego_mylist tnm 
                    inner join 
                        (select mbd.bu,mbd.ka,mm.member_name,mm.member_code from m_member mm inner join (select t1.parent_biz_division_code,t1.biz_division_code,t1.biz_division_name as ka,t2.biz_division_name as bu from m_business_division t1 inner join m_business_division t2 on t1.parent_biz_division_code = t2.biz_division_code) mbd 
                            on mm.biz_division_code = mbd.biz_division_code) sub on tnm.member_code = sub.member_code
                    inner join 
                        m_corporation mm on tnm.corporation_code = mm.corporation_code
                order by tnm.corporation_code,tnm.member_code 
                """
        cursor.execute(sql)
        return cursor.fetchall()

# --------------------------
#  getNegoCountdata 
#  t_nego_mylistに紐つく商談情報を取得する
#  Param prm_corp_datas:縦軸の情報
#  Param prm_date_list:日付の情報　横軸の比較対象となる
#  return final_out:最終的な配列
# -------------------------- 
def getNegoCountdata(prm_corp_datas,prm_date_list):

    start_date = prm_date_list[0]
    End_date = prm_date_list[30]
    End_next_date = End_date + timedelta(days=1) 
    
    final_out=[]

    conn = pymysql.connect(**db_params1)

    #縦軸がなくなるまで処理
    for main_containt in prm_corp_datas:

        #SQLパラメータ用意
        corp_cd = main_containt[8]
        member_cd = main_containt[5]

        param_list = []
        param_list.append(corp_cd)
        param_list.append(member_cd)
        param_list.append(start_date)
        param_list.append(End_next_date)

        #1行ごとの作業配列
        output_line=[]

        #縦軸のデータをあらかじめ格納する
        for i in range(0, 15):
            output_line.append(main_containt[i])
        
        with conn.cursor() as cursor:

            #顧客CD、担当者CDを渡してカウントデータを取得する
            sql = """
                    select summery_date,count
                    from t_nego_mylist_summary where corporation_code = %s and member_code = %s and summery_date >=DATE_FORMAT(%s,'%%Y-%%m-%%d') and summery_date<DATE_FORMAT(%s,'%%Y-%%m-%%d') order by summery_date
                    ;
                    """     
            cursor.execute(sql.format(),(param_list[0],param_list[1],param_list[2],param_list[3]))
            result_summary = cursor.fetchall()
            
            if len(result_summary) == 0:
                #顧客・担当のデータが存在しない場合は出力しない
                continue
            else:
                #ｋは進んだ日にちまでのINDEXを表す　データがあった日を飛ばして処理するため
                k=0
                output_line.append('商談数計算用であとでうわがかれるカラム')
                #集計期間の総合計を保持
                total_count_byline = 0
                #集計結果書き込みループ（横軸）
                for daily_data in result_summary:
                    #DBの日付
                    summary_date = daily_data[0]
                    #上記の日の商談数
                    summary_cnt=daily_data[1]

                    #あらかじめ取得した集計日とDBの集計日を突き合わせる　あったら書き込む　なかったら0を書き込む
                    for i in range(k, 31):
                        k=k+1
                        if prm_date_list[i].strftime('%Y-%m-%d')==summary_date.strftime('%Y-%m-%d'):
                            output_line.append(summary_cnt)
                            total_count_byline = total_count_byline +summary_cnt
                            break
                        else:
                            output_line.append('0')
                    
                    #集計結果を格納する    
                    output_line[15] = total_count_byline

        
        #最後に見つかったデータから最後までを0で埋める
        for i in range(k, 32):
            output_line.append('0')

        #結果を格納する
        final_out.append(output_line)

    return final_out;    

# --------------------------
#  outputCsv 
#  t_nego_mylistに紐つく商談情報を取得する
#  Param prm_header_data:ヘッダーデータ
#  Param prm_summary_datas:メインデータ
#  return なし
# -------------------------- 
def outputCsv(prm_header_data,prm_summary_datas):

    out_file_path = config['DEFAULT']['csv_out_path'] + "nego_summary_" + str(argvs) 
    out_file_utf8 = out_file_path + "_utf8.csv"
    out_file_sjis = out_file_path + ".csv"

    if len(prm_summary_datas) > 0 :
        df = pd.DataFrame(prm_summary_datas, columns=prm_header_data)
        df.to_csv(out_file_utf8, index=False, quoting=csv.QUOTE_ALL)
    else :
        # 空のCSVを出力する
        with open(out_file_utf8, 'w') as f:
            writer = csv.writer(f, lineterminator='\n')
            writer.writerow(prm_header_data) 
    cmd = "nkf -s %s > %s" % (out_file_utf8, out_file_sjis)
    subprocess.call(cmd, shell=True)

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

    logger.debug('[batch_t_nego_mylist_output_csv]を開始します。指定された日＝'+argvs)

    #対象日抽出
    date_list=makeDateList()

    #ヘッダー行を作成する
    header_data=makeHeaderLine(date_list)

    #企業・担当者データを取得する
    corp_datas=getCorpAndInchargedata()
    
    #企業・担当者データをもとに日ごとの商談カウントを取得する
    summary_datas=getNegoCountdata(corp_datas,date_list)

    #CSV出力する
    outputCsv(header_data,summary_datas)

    logger.debug('[batch_t_nego_mylist_output_csv]を終了します。')

if __name__ == '__main__':
    main()
# coding:utf-8

# ログのライブラリ
import logging
from logging import getLogger, StreamHandler, Formatter

# --------------------------------
# 1.loggerの設定
# --------------------------------
# loggerオブジェクトの宣言
logger = getLogger(__name__)

# loggerのログレベル設定(ハンドラに渡すエラーメッセージのレベル)
# ERRORを設定したためDEBUGは表示されない
logger.setLevel(logging.DEBUG)

# --------------------------------
# 2.handlerの設定
# --------------------------------
# handlerの生成
stream_handler = StreamHandler()

# handlerのログレベル設定(ハンドラが出力するエラーメッセージのレベル)
stream_handler.setLevel(logging.DEBUG)

# ログ出力フォーマット設定
handler_format = Formatter('%(asctime)s %(levelname)s: %(message)s')
stream_handler.setFormatter(handler_format)

# --------------------------------
# 3.loggerにhandlerをセット
# --------------------------------
logger.addHandler(stream_handler)

def debug(log):
    logger.debug(log)

def info(log):
    logger.info(log)

def warn(log):
    logger.warn(log)

def error(log):
    logger.error(log)

import logging

# --------------------------------
# 4.ファイル出力
# --------------------------------

#rootロガーを取得
logger = logging.getLogger()
logger.setLevel(logging.DEBUG)
#出力のフォーマットを定義
formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
#ファイルへ出力するハンドラーを定義
fh = logging.FileHandler(filename='log/log.txt')
fh.setLevel(logging.DEBUG)
fh.setFormatter(formatter)
#rootロガーにハンドラーを登録する
logger.addHandler(fh)
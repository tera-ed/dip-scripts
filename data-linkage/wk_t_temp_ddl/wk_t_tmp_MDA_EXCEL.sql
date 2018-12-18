/* Drop tables */
DROP TABLE IF EXISTS wk_t_tmp_mda_excel;

-- 週次バッチのLBCデータを一時的に管理するテーブル。
CREATE TABLE wk_t_tmp_mda_excel
(
  no                     CHAR(10),                  -- No
  media_name             VARCHAR(100),              -- 媒体名
  post_start_date        TEXT,                      -- 掲載開始日
  business_content       TEXT,                      -- 事業内容
  fuyokoumoku1           TEXT,                      -- 職種（募集媒体サンプル）
  corporation_name       TEXT,                      -- 会社名
  zip_code               TEXT,                      -- 郵便番号
  addr_prefe             TEXT,                      -- 都道府県
  address1               TEXT,                      -- 住所1
  address2               TEXT,                      -- 住所2
  address3               TEXT,                      -- 住所3
  tel                    TEXT,                      -- TEL
  section                TEXT,                      -- 担当部署
  corporation_emp_name   TEXT,                      -- 担当者名
  listed_marked          TEXT,                      -- 上場市場
  employee_number        TEXT,                      -- 従業員数
  capital_amount         TEXT,                      -- 資本金
  year_sales             TEXT,                      -- 売上高
  space                  TEXT,                      -- 広告スペース
  job_category           TEXT,                      -- 大カテゴリ
  job_class              VARCHAR(400),              -- 小カテゴリ
  post_count             BIGINT(10),                -- 掲載案件数
  dispatch_flag          TEXT,                      -- 派遣
  introduction_flag      TEXT,                      -- 紹介
  flag_count             BIGINT(10),                -- フラグ数
  fax                    TEXT,                      -- FAX
  data_get_date          TEXT,                      -- データ取得日
  fuyokoumoku2           TEXT,                      -- メール
  hp_url                 VARCHAR(500),              -- URL
  representative_name    VARCHAR(400),              -- 代表者名
  fuyokoumoku3           TEXT,                      -- 設立日
  fuyokoumoku4           TEXT,                      -- オプション(勤務形態等)
  fuyokoumoku5           TEXT,                      -- オプション(アピール項目等)
  fuyokoumoku6           TEXT,                      -- プレミアム画像
  memo                   TEXT,                      -- memo
  fuyokoumoku7           TEXT,                      -- 掲載URL
  fuyokoumoku8           TEXT,                      -- 請求取引先CD
  fuyokoumoku9           TEXT,                      -- COMP No
  fuyokoumoku10          TEXT                       -- 媒体種別詳細
);
CREATE INDEX wk_t_tmp_mda_excel_idx01 ON wk_t_tmp_mda_excel (no);

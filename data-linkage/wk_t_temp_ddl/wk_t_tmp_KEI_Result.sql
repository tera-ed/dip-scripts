/* Drop tables */
DROP TABLE IF EXISTS wk_t_tmp_kei_result;

-- 週次バッチのLBCデータを一時的に管理するテーブル。
CREATE TABLE wk_t_tmp_kei_result
(
  contract_code              CHAR(10),
  office_id                  CHAR(11),
  result_flg                 CHAR(2),
  detail_lvl                 CHAR(2),
  detail_content             CHAR(255)
);
CREATE INDEX wk_t_tmp_kei_result_idx01 ON wk_t_tmp_kei_result (contract_code);
CREATE INDEX wk_t_tmp_kei_result_idx02 ON wk_t_tmp_kei_result (office_id);
CREATE INDEX wk_t_tmp_kei_result_idx03 ON wk_t_tmp_kei_result (contract_code,office_id);

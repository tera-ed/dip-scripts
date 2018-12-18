/* Drop tables */
DROP TABLE IF EXISTS wk_t_tmp_crm_result;

-- 週次バッチのLBCデータを一時的に管理するテーブル。
CREATE TABLE wk_t_tmp_crm_result
(
  corporation_code           CHAR(9),
  office_id                  CHAR(11),
  result_flg                 VARCHAR(2),
  detail_lvl                 VARCHAR(2),
  detail_content             VARCHAR(255),
  PRIMARY KEY (corporation_code,office_id)
);
/* 性能次第で変更予定 */
CREATE INDEX wk_t_temp_crm_result_idx01 ON wk_t_tmp_crm_result (corporation_code);
CREATE INDEX wk_t_temp_crm_result_idx02 ON wk_t_tmp_crm_result (office_id);
CREATE INDEX wk_t_temp_crm_result_idx03 ON wk_t_tmp_crm_result (corporation_code,office_id);

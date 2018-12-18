/* Drop tables */
DROP TABLE IF EXISTS wk_t_tmp_quarter_integration;

-- 週次バッチのLBCデータを一時的に管理するテーブル。
CREATE TABLE wk_t_tmp_quarter_integration
(
  old_lbc                  CHAR(11),
  new_lbc                  CHAR(11)
);
CREATE INDEX wk_t_tmp_quarter_integration_idx01 ON wk_t_tmp_quarter_integration (old_lbc);
CREATE INDEX wk_t_tmp_quarter_integration_idx02 ON wk_t_tmp_quarter_integration (new_lbc);
CREATE INDEX wk_t_tmp_quarter_integration_idx03 ON wk_t_tmp_quarter_integration (old_lbc,new_lbc);

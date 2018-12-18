/* Drop tables */
DROP TABLE IF EXISTS wk_t_tmp_kng_result;

-- 週次バッチのLBCデータを一時的に管理するテーブル。
CREATE TABLE wk_t_tmp_kng_result
(
  kng_in_seq               CHAR(8),
  kng_in_crpnam            CHAR(80),
  kng_in_keiflg            CHAR(1),
  office_id                CHAR(11),
  result_flg               CHAR(2),
  detail_lvl               CHAR(2),
  detail_content           CHAR(255)
  );
CREATE INDEX wk_t_tmp_kng_result_idx01 ON wk_t_tmp_kng_result (kng_in_seq);
CREATE INDEX wk_t_tmp_kng_result_idx02 ON wk_t_tmp_kng_result (kng_in_crpnam);
CREATE INDEX wk_t_tmp_kng_result_idx03 ON wk_t_tmp_kng_result (office_id);

/* Drop tables */
DROP TABLE IF EXISTS wk_t_nayose_kng_result;

-- 名寄せ用 掲載不可情報とLBCの連携データ 格納テーブル
CREATE TABLE `wk_t_nayose_kng_result` (
  `kng_in_seq` char(8) DEFAULT NULL,
  `kng_in_crpnam` char(80) DEFAULT NULL,
  `kng_in_keiflg` char(1) DEFAULT NULL,
  `office_id` char(11) DEFAULT NULL,
  `result_flg` char(2) DEFAULT NULL,
  `detail_lvl` char(2) DEFAULT NULL,
  `detail_content` varchar(256) DEFAULT NULL,
  `delete_flag` int(1) DEFAULT NULL,
  KEY `wk_t_tmp_kng_result_idx01` (`kng_in_seq`),
  KEY `wk_t_tmp_kng_result_idx02` (`kng_in_crpnam`),
  KEY `wk_t_tmp_kng_result_idx03` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
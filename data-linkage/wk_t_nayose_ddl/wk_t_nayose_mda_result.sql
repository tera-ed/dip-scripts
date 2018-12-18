/* Drop tables */
DROP TABLE IF EXISTS wk_t_nayose_mda_result;

-- 名寄せ用 他媒体とLBCの連携データ 格納テーブル
CREATE TABLE `wk_t_nayose_mda_result` (
  `media_code` char(22) DEFAULT NULL,
  `office_id` char(11) DEFAULT NULL,
  `result_flg` char(2) DEFAULT NULL,
  `detail_lvl` char(2) DEFAULT NULL,
  `detail_content` varchar(256) DEFAULT NULL,
  `delete_flag` int(1) DEFAULT NULL,
  KEY `wk_t_tmp_mda_result_idx01` (`media_code`),
  KEY `wk_t_tmp_mda_result_idx02` (`office_id`),
  KEY `wk_t_tmp_mda_result_idx03` (`media_code`,`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
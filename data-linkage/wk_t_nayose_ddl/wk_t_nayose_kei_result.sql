/* Drop tables */
DROP TABLE IF EXISTS wk_t_nayose_kei_result;

-- 名寄せ用 掲載取引先コードとLBCの連携データ 格納テーブル
 CREATE TABLE `wk_t_nayose_kei_result` (
  `contract_code` char(10) DEFAULT NULL,
  `office_id` char(11) DEFAULT NULL,
  `result_flg` char(2) DEFAULT NULL,
  `detail_lvl` char(2) DEFAULT NULL,
  `detail_content` varchar(256) DEFAULT NULL,
  `delete_flag` int(1) DEFAULT NULL,
  KEY `wk_t_tmp_kei_result_idx01` (`contract_code`),
  KEY `wk_t_tmp_kei_result_idx02` (`office_id`),
  KEY `wk_t_tmp_kei_result_idx03` (`contract_code`,`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
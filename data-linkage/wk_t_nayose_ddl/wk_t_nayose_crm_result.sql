/* Drop tables */
DROP TABLE IF EXISTS wk_t_nayose_crm_result;

-- ñºäÒÇπóp å⁄ãqÇ∆LBCÇÃòAågåãâ  äiî[ÉeÅ[ÉuÉã
 CREATE TABLE `wk_t_nayose_crm_result` (
  `corporation_code` char(9) NOT NULL DEFAULT '',
  `office_id` char(11) NOT NULL DEFAULT '',
  `result_flg` varchar(2) DEFAULT NULL,
  `detail_lvl` varchar(2) DEFAULT NULL,
  `detail_content` varchar(256) DEFAULT NULL,
  `nayose_status` char(2) DEFAULT NULL,
  `delete_flag` int(1) DEFAULT NULL,
  PRIMARY KEY (`corporation_code`,`office_id`),
  KEY `wk_t_temp_crm_result_idx01` (`corporation_code`),
  KEY `wk_t_temp_crm_result_idx02` (`office_id`),
  KEY `wk_t_temp_crm_result_idx03` (`corporation_code`,`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
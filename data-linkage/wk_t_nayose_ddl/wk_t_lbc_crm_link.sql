/* Drop tables */
DROP TABLE IF EXISTS wk_t_lbc_crm_link;

-- 名寄せ情報を管理するテーブル。
CREATE TABLE wk_t_lbc_crm_link (
  corporation_code char(9) NOT NULL,             
  office_id char(11) NOT NULL,                   
  match_result char(1) DEFAULT NULL,             
  match_detail text,                             
  name_approach_code char(9) DEFAULT NULL,       
  name_approach_office_id char(11) DEFAULT NULL, 
  current_data_flag char(1) DEFAULT NULL,        
  delete_flag char(1) DEFAULT NULL,              
  KEY `wk_t_lbc_crm_link_idx01` (`corporation_code`),
  KEY `wk_t_lbc_crm_link_idx02` (`office_id`),
  KEY `wk_t_lbc_crm_link_idx03` (`corporation_code`,`office_id`),
  KEY `wk_t_lbc_crm_link_idx04` (`name_approach_code`),
  KEY `wk_t_lbc_crm_link_idx05` (`name_approach_office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

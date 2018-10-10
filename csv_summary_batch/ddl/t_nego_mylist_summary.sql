DROP TABLE `t_nego_mylist_summary`;
CREATE TABLE `t_nego_mylist_summary` (
  `corporation_code` varchar(9) NOT NULL,
  `member_code` varchar(6) NOT NULL,
  `summery_date` date NOT NULL,
  `count` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`corporation_code`,`member_code`,`summery_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
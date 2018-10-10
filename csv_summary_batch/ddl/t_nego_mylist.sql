DROP TABLE t_nego_mylist;														
CREATE TABLE `t_nego_mylist` (
  `corporation_code` varchar(9) NOT NULL,
  `member_code` varchar(6) NOT NULL,
  `business_priority` tinyint(4) DEFAULT NULL,
  `segment` text,
  `recolin_flag` tinyint(4) DEFAULT NULL,
  `incharge_flag` tinyint(4) DEFAULT NULL,
  `visible_flag` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`corporation_code`,`member_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
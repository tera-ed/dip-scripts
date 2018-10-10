/*  他媒体掲載企業テーブル */
DROP TABLE IF EXISTS t_media_posted_corporation;
CREATE TABLE `t_media_posted_corporation` (
  `corporation_code` char(9) NOT NULL,
  `last_update_date` DATETIME,
  PRIMARY KEY (`corporation_code`),
  KEY `t_media_posted_corporation_idx1` (`last_update_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
INSERT INTO t_media_posted_corporation SELECT corporation_code, max(update_date) FROM t_media_mass GROUP BY corporation_code;
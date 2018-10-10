/*  他媒体集計テーブル */
DROP TABLE IF EXISTS t_daily_summary_media_mass;
DROP TABLE IF EXISTS t_weekly_summary_media_mass;
CREATE TABLE `t_weekly_summary_media_mass` (
  `corporation_code` CHAR(9) NOT NULL,
  `compe_media_code`  char(10) NOT NULL,
  `post_end_date` DATETIME,
  `count` SMALLINT,
  `update_date` DATETIME,
  PRIMARY KEY (`corporation_code`,`compe_media_code`,`post_end_date`),
  KEY `t_daily_summary_media_mass_idx1` (`corporation_code`),
  KEY `t_daily_summary_media_mass_idx2` (`compe_media_code`),
  KEY `t_daily_summary_media_mass_idx3` (`post_end_date`),
  KEY `t_daily_summary_media_mass_idx4` (`update_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
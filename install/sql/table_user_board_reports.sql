CREATE TABLE /*_*/user_board_reports (
  `ubr_report_archive_id` int(11) NOT NULL,
  `ubr_reporter_curse_id` int(11) NOT NULL,
  `ubr_reported` datetime DEFAULT NULL,
  UNIQUE KEY `ubr_report_archive_id_ubr_reporter_curse_id` (`ubr_report_archive_id`,`ubr_reporter_curse_id`),
  KEY `ubr_report_curse_id` (`ubr_reporter_curse_id`),
  KEY `ubr_reported` (`ubr_reported`)
) /*$wgDBTableOptions*/;
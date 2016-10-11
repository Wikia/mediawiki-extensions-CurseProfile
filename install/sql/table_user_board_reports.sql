CREATE TABLE /*_*/user_board_reports (
  `ubr_report_archive_id` int(11) NOT NULL,
  `ubr_reporter_global_id` int(11) NOT NULL,
  `ubr_reported` datetime DEFAULT NULL
) /*$wgDBTableOptions*/;

ALTER TABLE `user_board_reports` ADD UNIQUE KEY `ubr_report_archive_id_ubr_reporter_global_id` (`ubr_report_archive_id`,`ubr_reporter_global_id`), ADD KEY `ubr_reported` (`ubr_reported`), ADD KEY `ubr_report_global_id` (`ubr_reporter_global_id`);
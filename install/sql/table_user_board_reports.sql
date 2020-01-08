CREATE TABLE /*_*/user_board_reports (
  `ubr_report_archive_id` int(11) NOT NULL,
  `ubr_reporter_user_id` int(11) NOT NULL DEFAULT '0',
  `ubr_reported` datetime DEFAULT NULL
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/user_board_reports
  ADD UNIQUE KEY `ubr_report_archive_id_ubr_reporter_global_id` (`ubr_report_archive_id`),
  ADD KEY `ubr_reported` (`ubr_reported`),
  ADD KEY `ubr_reporter_user_id` (`ubr_reporter_user_id`);
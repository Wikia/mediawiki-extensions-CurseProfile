CREATE TABLE /*_*/user_board_reports (
  `ubr_id` int(11) NOT NULL,
  `ubr_report_archive_id` int(11) NOT NULL,
  `ubr_reporter_user_id` int(11) NOT NULL DEFAULT '0',
  `ubr_reported` datetime DEFAULT NULL
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/user_board_reports
  ADD PRIMARY KEY (`ubr_id`),
  ADD UNIQUE KEY `ubr_report_archive_id_ubr_reporter_user_id` (`ubr_report_archive_id`,`ubr_reporter_user_id`),
  ADD KEY `ubr_reported` (`ubr_reported`),
  ADD KEY `ubr_reporter_user_id` (`ubr_reporter_user_id`);

ALTER TABLE /*_*/user_board_reports
  MODIFY `ubr_id` int(11) NOT NULL AUTO_INCREMENT;
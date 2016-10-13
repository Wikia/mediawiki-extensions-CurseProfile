ALTER TABLE /*_*/user_board_reports CHANGE `ubr_reporter_curse_id` `ubr_reporter_global_id` INT(11) NOT NULL;
ALTER TABLE /*_*/user_board_report_archives CHANGE `ra_curse_id_from` `ra_global_id_from` INT(11) NOT NULL DEFAULT '0';

DROP INDEX /*i*/ubr_report_curse_id ON /*_*/user_board_reports;
DROP INDEX /*i*/ubr_reporter_curse_id ON /*_*/user_board_reports;
CREATE INDEX /*i*/ubr_reporter_global_id ON /*_*/user_board_reports (ubr_reporter_global_id);

DROP INDEX /*i*/ubr_report_archive_id_ubr_reporter_curse_id ON /*_*/user_board_reports;
ALTER TABLE /*_*/user_board_reports ADD UNIQUE KEY `ubr_report_archive_id_ubr_reporter_global_id` (`ubr_report_archive_id`,`ubr_reporter_global_id`);

DROP INDEX /*i*/ra_curse_id_from ON /*_*/user_board_report_archives;
CREATE INDEX /*i*/ra_global_id_from ON /*_*/user_board_report_archives (ra_global_id_from);
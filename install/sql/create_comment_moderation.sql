CREATE TABLE /*_*/user_board_report_archives (
  `ra_id` int(11) PRIMARY KEY auto_increment,
  `ra_comment_id` int(11) NOT NULL default '0',
  `ra_curse_id_from` int(11) NOT NULL default '0',
  `ra_comment_text` text NOT NULL,
  `ra_last_edited` datetime default NULL,
  `ra_first_reported` datetime default NULL,
  `ra_action_taken` int(1) default NULL,
  `ra_action_taken_by` int(11) default NULL/* curse id of acting moderator */
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/ra_comment_report ON /*_*/user_board_report_archives (ra_comment_id,ra_last_edited);
CREATE INDEX /*i*/ra_user_id_from ON /*_*/user_board_report_archives (ra_user_id_from);
CREATE INDEX /*i*/ra_curse_id_from ON /*_*/user_board_report_archives (ra_curse_id_from);
CREATE INDEX /*i*/ra_first_reported ON /*_*/user_board_report_archives (ra_first_reported);

CREATE TABLE /*_*/user_board_reports (
  `ubr_report_archive_id` int(11) NOT NULL,
  `ubr_reporter_id` int(11) NOT NULL,
  `ubr_reporter_curse_id` int(11) NOT NULL,
  `ubr_reported` datetime default NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/ubr_report_archive_id ON /*_*/user_board_reports (ubr_report_archive_id);
CREATE INDEX /*i*/ubr_reported ON /*_*/user_board_reports (ubr_reported);

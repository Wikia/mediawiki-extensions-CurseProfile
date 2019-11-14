CREATE TABLE /*_*/user_board_purge_archive (
  `ubpa_id` int(11) PRIMARY KEY auto_increment,
  `ubpa_user_id` int(11) NOT NULL default '0',
  `ubpa_user_from_id` int(11) NOT NULL default '0',
  `ubpa_admin_id` int(11) DEFAULT NULL,
  `ubpa_comment_id` int(11) NOT NULL default '0',
  `ubpa_comment` text NOT NULL,
  `ubpa_reason` text NOT NULL,
  `ubpa_purged_at` datetime DEFAULT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ubpa_user_id ON        /*_*/user_board_purge_archive (ubpa_user_id);
CREATE INDEX /*i*/ubpa_user_from_id ON   /*_*/user_board_purge_archive (ubpa_user_from_id);

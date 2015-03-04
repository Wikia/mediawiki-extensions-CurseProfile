CREATE TABLE /*_*/user_board (
  `ub_id` int(11) PRIMARY KEY auto_increment,
  `ub_in_reply_to` INT(11) NOT NULL DEFAULT '0',
  `ub_user_id` int(11) NOT NULL default '0',
  `ub_user_name` varchar(255) NOT NULL default '',
  `ub_user_id_from` int(11) NOT NULL default '0',
  `ub_user_name_from` varchar(255) NOT NULL default '',
  `ub_message` text NOT NULL,
  `ub_type` int(5) default '0',
  `ub_date` datetime default NULL,
  `ub_last_reply` datetime default NULL,
  `ub_edited` datetime deftult NULL,
  `ub_admin_acted` int(11) DEFAULT NULL COMMENT 'Curse ID of admin who moderated',
  `ub_admin_acted_at` datetime DEFAULT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/ub_user_id ON      /*_*/user_board (ub_user_id);
CREATE INDEX /*i*/ub_user_id_from ON /*_*/user_board (ub_user_id_from);
CREATE INDEX /*i*/ub_type ON         /*_*/user_board (ub_type);
CREATE INDEX /*i*/ub_in_reply_to ON  /*_*/user_board (ub_in_reply_to);

CREATE TABLE /*_*/user_profile (
  `up_user_id` int(5) NOT NULL PRIMARY KEY default '0',
  `up_location_city` varchar(255) default NULL,
  `up_location_state` varchar(100) default NULL,
  `up_location_country` varchar(255) default NULL,
  `up_relationship` int(5) NOT NULL default '0',
  `up_occupation` varchar(255) default '',
  `up_about` text,
  `up_last_seen` datetime default NULL,
  `up_type` int(5) NOT NULL default '1',
  `up_steam_profile` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `up_xbl_profile` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `up_psn_profile` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL
) /*$wgDBTableOptions*/;

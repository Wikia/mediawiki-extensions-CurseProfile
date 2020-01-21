CREATE TABLE /*_*/user_board (
  `ub_id` int(11) NOT NULL,
  `ub_in_reply_to` int(11) NOT NULL DEFAULT '0',
  `ub_user_id` int(11) NOT NULL DEFAULT '0',
  `ub_user_name` varbinary(255) NOT NULL DEFAULT '',
  `ub_user_id_from` int(11) NOT NULL DEFAULT '0',
  `ub_user_name_from` varbinary(255) NOT NULL DEFAULT '',
  `ub_message` blob NOT NULL,
  `ub_type` int(5) DEFAULT '0',
  `ub_date` datetime DEFAULT NULL,
  `ub_last_reply` datetime DEFAULT NULL,
  `ub_edited` datetime DEFAULT NULL,
  `ub_admin_acted_user_id` int(11) DEFAULT NULL,
  `ub_admin_acted_at` datetime DEFAULT NULL
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/user_board
  ADD PRIMARY KEY (`ub_id`),
  ADD KEY `ub_user_id` (`ub_user_id`),
  ADD KEY `ub_user_id_from` (`ub_user_id_from`),
  ADD KEY `ub_type` (`ub_type`),
  ADD KEY `ub_in_reply_to` (`ub_in_reply_to`);

ALTER TABLE /*_*/user_board
  MODIFY `ub_id` int(11) NOT NULL AUTO_INCREMENT;
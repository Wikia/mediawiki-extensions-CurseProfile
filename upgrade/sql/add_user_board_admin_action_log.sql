ALTER TABLE /*_*/user_board
ADD `ub_admin_acted` int(11) DEFAULT NULL COMMENT 'Curse ID of admin who moderated',
ADD `ub_admin_acted_at` datetime DEFAULT NULL;

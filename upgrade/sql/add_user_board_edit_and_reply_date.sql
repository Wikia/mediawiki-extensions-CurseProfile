ALTER TABLE /*_*/user_board
ADD `ub_last_reply` datetime default NULL AFTER `ub_date`,
ADD `ub_edited` datetime default NULL AFTER `ub_last_reply`;

/*
Update ub_last_reply dates for existing comments
Select into a temprary table because mysql can't select from a table being updated >:|
*/
CREATE TEMPORARY TABLE updated_dates SELECT
	`ub_in_reply_to` as `ub_id`,
	MAX(`ub_date`) as `ub_last_reply`
FROM /*_*/user_board WHERE `ub_in_reply_to` > 0 GROUP BY `ub_in_reply_to`;

UPDATE /*_*/user_board as ub SET ub.`ub_last_reply` = (
	SELECT d.`ub_last_reply` FROM updated_dates as d WHERE ub.`ub_id`=d.`ub_id`
);

DROP TABLE updated_dates;


ALTER TABLE /*_*/user_board
ADD `ub_in_reply_to` INT(11) NOT NULL DEFAULT '0' AFTER `ub_id`;

CREATE INDEX /*i*/ub_in_reply_to ON /*_*/user_board (ub_in_reply_to);

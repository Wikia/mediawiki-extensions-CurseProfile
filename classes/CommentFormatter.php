<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

/**
 * Class that formats notifications for profile comments
 */
class CommentFormatter extends \EchoBasicFormatter {
	const MAX_PREVIEW_LEN = 80; // only preview first 80 characters of message (is clipped by substr in CommentBoard)

	protected function formatPayload($payload, $event, $user) {
		switch ($payload) {
			case '':
				$extra = $event->getExtra();
				if (!isset($extra['comment_text'])) {
					return '';
				} else {
					return $extra['comment_text'] . (strlen($extra['comment_text']) == self::MAX_PREVIEW_LEN ? '...' : '');
				}

			default:
				return parent::formatPayload($payload, $event, $user);
		}
	}
}

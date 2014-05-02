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
 * Class that formats notifications for profile comments and friend requests
 */
class NotificationFormatter extends \EchoBasicFormatter {
	const MAX_PREVIEW_LEN = 80; // only preview first 80 characters of message (is clipped by substr in CommentBoard)

	/**
	 * Turns keywords for payload pieces into actual content.
	 *
	 * @param	string	the keyword for a payload
	 * @param	object	the event object
	 * @param	user	the user to whom this event will be delivered
	 * @return	string	the content for the given payload keyword
	 */
	protected function formatPayload($payload, $event, $user) {
		switch ($payload) {
			case 'comment-text':
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

	/**
	 * Processes parameter keywords into data for a message before rendering a notification
	 *
	 * @param	object	EchoEvent object
	 * @param	string	param keyword to be given a value
	 * @param	object	the mediawiki message object in need of this param
	 * @param	object	the user to whom this notification will be delivered
	 * @return	void	deliver the appropriate value to the message via ->params() instead of returning a value
	 */
	protected function processParam($event, $param, $message, $user) {
		switch ($param) {
			case 'gamepedia-footer':
				$message->params(wfMessage('emailfooter-gamepedia')->text());
				break;

			default:
				parent::processParam($event, $param, $message, $user);
		}
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		CurseProfile
 * @link		https://gitlab.com/hydrawiki
 *
**/
namespace CurseProfile\MWEcho;

use EchoModelFormatter;

/**
 * Class that formats notifications for profile comments and friend requests
 */
class NotificationFormatter extends EchoModelFormatter {
	// only preview first 80 characters of message (is clipped by substr in CommentBoard)
	const MAX_PREVIEW_LEN = 80;

	/**
	 * Turns keywords for payload pieces into actual content.
	 *
	 * @param	string	$payload the keyword for a payload
	 * @param	object	$event the event object
	 * @param	User	$user the user to whom this event will be delivered
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
	 * @access	protected
	 * @param	object	$event EchoEvent object
	 * @param	string	$param Parameter keyword to be given a value
	 * @param	object	$message The mediawiki message object in need of this parameter.
	 * @param	object	$user The user to whom this notification will be delivered.
	 * @return	void	Deliver the appropriate value to the message via ->params() instead of returning a value.
	 */
	protected function processParam($event, $param, $message, $user) {
		$extra = $event->getExtra();
		switch ($param) {
			case 'gamepedia-footer':
				$message->params(wfMessage('emailfooter-gamepedia')->text());
				break;
			case 'comment_id':
				$message->params(isset($extra['comment_id']) ? $extra['comment_id'] : 0);
				break;
			default:
				parent::processParam($event, $param, $message, $user);
				break;
		}
	}

	/**
	 * Helper function for getLink().
	 *
	 * @access	public
	 * @param	object	EchoEvent $event
	 * @param	object	User $user The user receiving the notification
	 * @param	string	$destination The destination type for the link, e.g. 'agent'
	 * @return	array	Including target and query parameters. Note that target can be either a Title or a full url.
	 */
	protected function getLinkParams($event, $user, $destination) {
		$target = null;
		$query = [];
		$title = $event->getTitle();

		switch ($destination) {
			case "friendship":
				break;
			case "comment":
				break;
			default:
				return parent::getLinkParams($event, $user, $destination);
		}
		return [$target, $query];
	}
}

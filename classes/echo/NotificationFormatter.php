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
namespace CurseProfile\MWEcho;

/**
 * Class that formats notifications for profile comments and friend requests
 */
class NotificationFormatter extends \EchoModelFormatter {
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
	 * @access	protected
	 * @param	object	EchoEvent object
	 * @param	string	Parameter keyword to be given a value
	 * @param	object	The mediawiki message object in need of this parameter.
	 * @param	object	The user to whom this notification will be delivered.
	 * @return	void	Deliver the appropriate value to the message via ->params() instead of returning a value.
	 */
	protected function processParam($event, $param, $message, $user) {
		switch ($param) {
			case 'gamepedia-footer':
				$message->params(wfMessage('emailfooter-gamepedia')->text());
				break;
			case 'comment-id':
				$extra = $event->getExtra();
				$message->params( $extra['comment_id'] ? $extra['comment_id'] : 0 );
				break;
			default:
				parent::processParam($event, $param, $message, $user);
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
			case "friendship-request":
				break;
			case "profile-comment":
				break;
			default:
				return parent::getLinkParams($event, $user, $destination);
				break;
		}
		return [$target, $query];
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @package   CurseProfile
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile\MWEcho;

use EchoEventPresentationModel;

/**
 * Class that formats notifications for profile comments and friend requests
 */
class CommentPresentationModel extends EchoEventPresentationModel {
	/**
	 * Get a message object and add the performer's name as
	 * a parameter. It is expected that subclasses will override
	 * this.
	 *
	 * @access public
	 * @return Message
	 */
	public function getHeaderMessage() {
		$message = $this->getMessageWithAgent($this->getHeaderMessageKey());
		if ($this->event->getTitle() !== null) {
			$message->params($this->event->getTitle()->getFullText());
		}

		return $message;
	}

	/**
	 * Return the icon used for this notification.
	 *
	 * @access public
	 * @return string	The symbolic icon name as defined in $wgEchoNotificationIcons.
	 */
	public function getIconType() {
		return 'mention';
	}

	/**
	 * Array of primary link details, with possibly-relative URL & label.
	 *
	 * @access public
	 * @return array|bool Array of link data, or false for no link:
	 *                    ['url' => (string) url, 'label' => (string) link text (non-escaped)]
	 */
	public function getPrimaryLink() {
		if ($this->event->getTitle() !== null) {
			return [
				'url' => $this->event->getTitle()->getLocalURL() . ($this->event->getExtraParam('comment_id') > 0 ? '#comment' . $this->event->getExtraParam('comment_id') : null),
				'label' => $this->msg('notification-link-text-view-comment')->text(),
			];
		} else {
			return [];
		}
	}

	/**
	 * Array of secondary link details, including possibly-relative URLs, label,
	 * description & icon name.
	 *
	 * @access public
	 * @return array Array of links in the format of:
	 *               [['url' => (string) url,
	 *                 'label' => (string) link text (non-escaped),
	 *                 'description' => (string) descriptive text (non-escaped),
	 *                 'icon' => (bool|string) symbolic icon name (or false if there is none),
	 *                 'prioritized' => (bool) true if the link should be outside the
	 *                                  action menu, false for inside)],
	 *                ...]
	 *
	 *               Note that you should call array_values(array_filter()) on the
	 *               result of this function (FIXME).
	 */
	public function getSecondaryLinks() {
		return [];
	}
}

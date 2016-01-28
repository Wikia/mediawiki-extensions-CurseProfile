<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile\MWEcho;

/**
 * Class that formats notifications for profile comments and friend requests
 */
class CommentPresentationModel extends \EchoEventPresentationModel {
	/**
	 * Return the icon type.
	 *
	 * @access	public
	 * @return string The symbolic icon name as defined in $wgEchoNotificationIcons
	 */
	public function getIconType() {
		return 'gratitude';
	}

	/**
	 * Return a message object with the agent($1) and target($3) user page as pamaraters.
	 *
	 * @access	public
	 * @return	Message
	 */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();
		if ($this->event->getTitle() !== null) {
			$msg->params($this->event->getTitle()->getFullText());
		}

		return $msg;
	}

	/**
	 * Array of primary link details, with possibly-relative URL & label.
	 *
	 * @access	public
	 * @return array|bool Array of link data, or false for no link:
	 *                    ['url' => (string) url, 'label' => (string) link text (non-escaped)]
	 */
	public function getPrimaryLink() {
		if ($this->event->getTitle() !== null) {
			return [
				'url' => $this->event->getTitle()->getLocalURL().($this->event->getExtraParam('comment_id') > 0 ? '#comment'.$this->event->getExtraParam('comment_id') : null),
				'label' => $this->msg('notification-link-text-view-comment')->text(),
			];
		} else {
			return [];
		}
	}

	/**
	 * Array of secondary link details, including possibly-relative URLs, label, description & icon name.
	 *
	 * @access	public
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
		return [$this->getAgentLink()];
	}
}


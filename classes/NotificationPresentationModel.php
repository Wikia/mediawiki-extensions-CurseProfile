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
namespace CurseProfile;

/**
 * Class that formats notifications for profile comments and friend requests
 */
class NotificationPresentationModel extends \EchoEventPresentationModel {
	/**
	 * @return string The symbolic icon name as defined in $wgEchoNotificationIcons
	 */
	public function getIconType() {
		return 'gratitude';
	}

	/*public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();
		$msg->params( $this->event->getTitle()->getPrefixedText() );

		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}*/

	/**
	 * Array of primary link details, with possibly-relative URL & label.
	 *
	 * @return array|bool Array of link data, or false for no link:
	 *                    ['url' => (string) url, 'label' => (string) link text (non-escaped)]
	 */
	public function getPrimaryLink() {
		return array(
			'url' => $this->event->getTitle()->getLocalURL( array(
				'oldid' => 'prev',
				'diff' => $this->event->getExtraParam( 'revid' )
			) ),
			'label' => $this->msg( 'notification-link-text-view-message' )->text(),
		);
	}

	/**
	 * Array of secondary link details, including possibly-relative URLs, label,
	 * description & icon name.
	 *
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
		return array();
	}
}


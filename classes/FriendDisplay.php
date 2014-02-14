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
 * A class to manage displaying a list of friends on a user profile
 */
class FriendDisplay {
	/**
	 * Generates an array to be inserted into the nav links of the page
	 *
	 * @param	int		user id of the profile page being viewed
	 * @param	array	reference to the links array into which the links will be inserted
	 * @return	void
	 */
	public static function addFriendLink($user_id = '', &$links) {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return;
		}

		global $wgUser;
		if (!$wgUser->isLoggedIn() || $wgUser->getID() == $user_id) {
			return;
		}

		$mouse = CP::loadMouse();
		$curse_id = CP::curseIDfromUserID($wgUser->getID());
		$friendship = new Friendship($curse_id);
		$relationship = $friendship->getRelationship(CP::curseIDfromUserID($user_id));

		switch ($relationship) {
			case Friendship::STRANGERS:
			$links['views']['add_friend'] = [
				'class'   => false,
				'href'    => "/Special:AddFriend/$user_id",
				'text'    => wfMessage('friendrequestsend')->plain(),
			];
			break;

			case Friendship::REQUEST_SENT:
			$links['views']['add_friend'] = [
				'class'   => 'friend-request-sent',
				'href'    => "/Special:RemoveFriend/$user_id",
				'text'    => wfMessage('friendrequestcancel')->plain(),
			];
			break;

			case Friendship::REQUEST_RECEIVED:
			$links['actions']['add_friend'] = [
				'class'   => 'friend-request-confirm',
				'href'    => "/Special:ConfirmFriend/$user_id",
				'text'    => wfMessage('confirmfriend-response')->plain(),
			];
			$links['actions']['ignore_friend'] = [
				'class'   => 'friend-request-ignore',
				'href'    => "/Special:ConfirmFriend/$user_id",
				'text'    => wfMessage('ignorefriend-response')->plain(),
			];
			break;

			case Friendship::FRIENDS:
			$links['views']['remove_friend'] = [
				'class'   => 'friend-request-sent',
				'href'    => "/Special:RemoveFriend/$user_id",
				'text'    => wfMessage('removefriend')->plain(),
			];
			break;

			default:
			return;
		}
	}

	/**
	 * @return	string	html button stuff
	 */
	public static function addFriendButton($user_id = '') {
		// reuse logic from the other function
		$links = [];
		self::addFriendLink($user_id, $links);

		if (isset($links['views'])) {
			$links = $links['views'];
		} elseif (isset($links['actions'])) {
			$links = $links['actions'];
		}

		$HTML = '';
		foreach ($links as $link) {
			$HTML .= "<button data-href='{$link['href']}' class='linksub'>{$link['text']}</button>";
		}

		return $HTML;
	}

	public static function count(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		$friendship = new Friendship(CP::curseIDfromUserID($user_id));
		$friends = $friendship->getFriends();
		return count($friends);
	}

	public static function friendlist(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		$mouse = CP::loadMouse();
		$friendship = new Friendship(CP::curseIDfromUserID($user_id));
		$friends = $friendship->getFriends();
		if (count($friends) == 0) {
			return '';
		}

		$friendDataRes = $mouse->DB->select([
			'select'	=> 'u.*',
			'from'		=> ['user' => 'u'],
			'where'		=> 'curse_id IN ('.implode(',',$friends).')',
			'order'		=> 'u.user_touched DESC',
			'limit'		=> '10'
		]);

		$friendData = [];
		while ($row = $mouse->DB->fetch($friendDataRes)) {
			$friendData[] = $row;
		}

		if (count($friendData) == 0) {
			return wfMessage('nofriends')->escaped();
		}


		$HTML = '
		<ul class="friends">';
		foreach ($friendData as $friend) {
			$fUser = \User::newFromId($friend['user_id']);
			$HTML .= '<li>';
			$HTML .= ProfilePage::userAvatar($nothing, 32, $fUser->getEmail(), $fUser->getName())[0];
			$HTML .= ' '.CP::userLink($friend['user_id']);
			$HTML .= '</li>';
		}
		$HTML .= '
		</ul>';

		return [
			$HTML,
			'isHTML' => true,
		];
	}
}

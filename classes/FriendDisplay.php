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
	public static function addFriendLink(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}

		global $wgUser;
		if (!$wgUser->getID()) {
			return '';
		} elseif ($wgUser->getID() == $user_id) {
			return ['<div class="button"><a href="/Special:EditProfile">'.wfMessage('editprofile')->escaped().'</a></div>', 'isHTML' => true];
		}

		//if (not logged in) return '';

		$mouse = CP::loadMouse();
		$curse_id = CP::curseIDfromUserID($wgUser->getID());
		$friendship = new Friendship($curse_id);
		$relationship = $friendship->getRelationship(CP::curseIDfromUserID($user_id));

		switch ($relationship) {
			case Friendship::STRANGERS:
			return ['<div class="button"><a href="/Special:AddFriend/'.$user_id.'">'.wfMessage('addfriend')->escaped().'</a></div>', 'isHTML' => true];

			case Friendship::REQUEST_SENT:
			return ['<div class="button">'.wfMessage('friendrequestsent')->escaped().'</div>', 'isHTML' => true];

			case Friendship::REQUEST_RECEIVED:
			return ['<div class="button">Friendship Requested: <a href="/Special:ConfirmFriend/'.$user_id.'">'.wfMessage('confirmfriend-response')->escaped().'</a> / <a href="/Special:IgnoreFriend/'.$user_id.'">'.wfMessage('ignorefriend-response')->escaped().'</a></div>', 'isHTML' => true];

			case Friendship::FRIENDS:
			return ['<div class="button">'.wfMessage('alreadyfriends')->escaped().'</div>', 'isHTML' => true];

			default:
			return '';
		}
	}

	public static function count(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$friendship = new Friendship(CP::curseIDfromUserID($user_id));
		$friends = $friendship->getFriends();
		return count($friends);
	}

	public static function friendlist(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
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

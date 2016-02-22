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
	 * @param	integer	user id or curse id of the profile page being viewed
	 * @param	array	reference to the links array into which the links will be inserted
	 * @param	boolean	determines the function of the first arg
	 * @return	void
	 */
	public static function addFriendLink($user_id = '', &$links, $isCurseId = false) {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return;
		}

		global $wgUser;
		$wgUser->load();
		$curseUser = \CurseAuthUser::getInstance($wgUser);
		if (!$wgUser->isLoggedIn()
			|| ($wgUser->getID() == $user_id && !$isCurseId)
			|| ($curseUser->GetId() == $user_id && $isCurseId))
		{
			return;
		}

		$curseId = $curseUser->getId();
		$friendship = new Friendship($curseId);
		if ($isCurseId) {
			$links['curse_id'] = $user_id;
			$user = \CurseAuthUser::newUserFromGlobalId($user_id);
		} else {
			$links['curse_id'] = \CurseAuthUser::globalIdFromUserId($user_id);
			$user = \User::newFromId($user_id);
		}
		$user->load();
		$relationship = $friendship->getRelationship($links['curse_id']);

		switch ($relationship) {
			case Friendship::STRANGERS:
			$links['views']['add_friend'] = [
				'action'  => 'send',
				'class'   => false,
				'href'    => "/Special:AddFriend/$user_id",
				'text'    => wfMessage('friendrequestsend')->plain(),
			];
			break;

			case Friendship::REQUEST_SENT:
			$links['views']['add_friend'] = [
				'action'  => 'remove',
				'class'   => 'friend-request-sent',
				'href'    => "/Special:RemoveFriend/$user_id",
				'text'    => wfMessage('friendrequestcancel')->plain(),
			];
			break;

			case Friendship::REQUEST_RECEIVED:
			$links['actions']['add_friend'] = [
				'action'  => 'confirm',
				'class'   => 'friend-request-confirm',
				'href'    => "/Special:ConfirmFriend/$user_id",
				'text'    => wfMessage('confirmfriend-response')->plain(),
			];
			$links['actions']['ignore_friend'] = [
				'action'  => 'ignore',
				'class'   => 'friend-request-ignore',
				'href'    => "/Special:ConfirmFriend/$user_id",
				'text'    => wfMessage('ignorefriend-response')->plain(),
			];
			break;

			case Friendship::FRIENDS:
			$links['views']['remove_friend'] = [
				'action'  => 'remove',
				'class'   => 'friend-request-sent',
				'href'    => "/Special:RemoveFriend/$user_id",
				'text'    => wfMessage('removefriend')->plain(),
				'confirm' => wfMessage('friendrequestremove-prompt', $user->getName())->plain(),
			];
			break;

			default:
			return;
		}
	}

	/**
	 * @param	integer	user id or curse id of the user on which the buttons will act
	 * @param	boolean	determines the function of the previous arg
	 * @return	string	html button stuff
	 */
	public static function friendButtons($user_id = '', $isCurseId = false) {
		// reuse logic from the other function
		$links = [];
		self::addFriendLink($user_id, $links, $isCurseId);

		if (isset($links['actions'])) {
			$links['views'] = $links['actions'];
		}

		$HTML = '';

		if (count($links['views'])) {
			foreach ($links['views'] as $link) {
				$attribs = [
					'class' => 'friendship-action',
					'data-action' => $link['action'],
					'data-id' => $links['curse_id'],
				];
				if (isset($link['confirm'])) {
					$attribs['data-confirm'] = $link['confirm'];
				}
				$HTML .= \Html::rawElement('button', $attribs, $link['text']);
			}
		}

		return $HTML;
	}

	public static function addFriendButton($user_id = '', $isCurseId = false) {
		return '<div class="friendship-container">'.self::friendButtons($user_id, $isCurseId).'</div>';
	}

	public static function count(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		$friendship = new Friendship(\CurseAuthUser::globalIdFromUserId($user_id));
		$friends = $friendship->getFriends();
		return count($friends);
	}

	public static function friendlist(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		$friendship = new Friendship(\CurseAuthUser::globalIdFromUserId($user_id));
		$friends = $friendship->getFriends();
		if (count($friends) == 0) {
			return '';
		}

		return [
			self::listFromArray($friends),
			'isHTML' => true,
		];
	}

	/**
	 * Creates a UL html list from an array of curse IDs. The callback function can insert extra html in the LI tags.
	 *
	 * @param	array	curse IDs
	 * @param	bool	signature: callback($curse_id, $userObj) returns string
	 * @return	string	html UL list
	 */
	public static function listFromArray($curseIDs = [], $manageButtons = false) {
		$db = CP::getDb(DB_MASTER);
		$results = $db->select(
			['user_global'],
			['user_global.*', 'user.user_touched'],
			['global_id' => $curseIDs],
			__METHOD__,
			[
				'LIMIT'		=> 10,
				'ORDER BY'	=> 'user_touched DESC'
			],
			[
				'user' => [
					'INNER JOIN', 'user.user_id = user_global.user_id'
				]
			]
		);

		$friendData = [];
		while ($row = $results->fetchRow()) {
			$friendData[] = $row;
		}

		if (count($friendData) == 0) {
			return wfMessage('nofriends')->escaped();
		}

		$HTML = '
		<ul class="friends">';
		foreach ($friendData as $friend) {
			$fUser = \User::newFromId($friend['user_id']);
			if (!$fUser->getId()) {
				//Just silently drop if the user is actually missing.
				continue;
			}
			$HTML .= '<li>';
			$HTML .= ProfilePage::userAvatar($nothing, 32, $fUser->getEmail(), $fUser->getName())[0];
			$HTML .= ' '.CP::userLink($friend['user_id']);
			if ($manageButtons) {
				$HTML .= ' '.self::addFriendButton($friend['curse_id'], true);
			}
			$HTML .= '</li>';
		}
		$HTML .= '
		</ul>';

		return $HTML;
	}
}

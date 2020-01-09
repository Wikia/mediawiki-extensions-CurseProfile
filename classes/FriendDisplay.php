<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use Html;
use Parser;
use User;

/**
 * A class to manage displaying a list of friends on a user profile
 */
class FriendDisplay {
	/**
	 * Generates an array to be inserted into the nav links of the page
	 *
	 * @param  integer $userId User ID of the profile page being viewed
	 * @param  array   &$links reference to the links array into which the links will be inserted
	 * @return void
	 */
	public static function addFriendLink(int $userId, array &$links) {
		$user = User::newFromId($userId);
		if (!$user || $user->isAnon()) {
			return;
		}

		$friendship = new Friendship($user);

		$links['user_id'] = $user->getId();
		$relationship = $friendship->getRelationship($links['user_id']);

		switch ($relationship) {
			case Friendship::STRANGERS:
				$links['views']['add_friend'] = [
					'action'  => 'send',
					'class'   => false,
					'href'    => "/Special:AddFriend/$userId",
					'text'    => wfMessage('friendrequestsend')->plain(),
				];
				break;

			case Friendship::REQUEST_SENT:
				$links['views']['add_friend'] = [
					'action'  => 'remove',
					'class'   => 'friend-request-sent',
					'href'    => "/Special:RemoveFriend/$userId",
					'text'    => wfMessage('friendrequestcancel')->plain(),
				];
				break;

			case Friendship::REQUEST_RECEIVED:
				$links['actions']['add_friend'] = [
					'action'  => 'confirm',
					'class'   => 'friend-request-confirm',
					'href'    => "/Special:ConfirmFriend/$userId",
					'text'    => wfMessage('confirmfriend-response')->plain(),
				];
				$links['actions']['ignore_friend'] = [
					'action'  => 'ignore',
					'class'   => 'friend-request-ignore',
					'href'    => "/Special:ConfirmFriend/$userId",
					'text'    => wfMessage('ignorefriend-response')->plain(),
				];
				break;

			case Friendship::FRIENDS:
				$links['views']['remove_friend'] = [
					'action'  => 'remove',
					'class'   => 'friend-request-sent',
					'href'    => "/Special:RemoveFriend/$userId",
					'text'    => wfMessage('removefriend')->plain(),
					'confirm' => wfMessage('friendrequestremove-prompt', $user->getName())->plain(),
				];
				break;

			default:
				return;
		}
	}

	/**
	 * Friend Button Stuff
	 *
	 * @param  integer $userId user id or curse id of the user on which the buttons will act
	 * @return string HTML button stuff
	 */
	public static function friendButtons(int $userId) {
		// reuse logic from the other function
		$links = [];
		self::addFriendLink($userId, $links);

		if (isset($links['actions'])) {
			$links['views'] = $links['actions'];
		}

		$html = '';

		if (isset($links['views']) && count($links['views'])) {
			foreach ($links['views'] as $link) {
				$attribs = [
					'class' => 'friendship-action',
					'data-action' => $link['action'],
					'data-id' => $links['user_id'],
				];
				if (isset($link['confirm'])) {
					$attribs['data-confirm'] = $link['confirm'];
				}
				$html .= Html::rawElement('button', $attribs, $link['text']);
			}
		}

		return $html;
	}

	/**
	 * Adds a Friend Button
	 *
	 * @param integer $userId
	 *
	 * @return string
	 */
	public static function addFriendButton(int $userId) {
		return '<div class="friendship-container">' . self::friendButtons($userId) . '</div>';
	}

	/**
	 * Get the user's friend count based on their local user ID.
	 *
	 * @param Parser|null $parser - Not used, but the parser will pass it regardless.
	 * @param integer     $userId Local User ID
	 *
	 * @return integer Number of friends.
	 */
	public static function count(?Parser $parser = null, int $userId) {
		$user = User::newFromId($userId);

		if (!$user) {
			return 0;
		}

		$friendship = new Friendship($user);
		$friends = $friendship->getFriends();

		return count($friends);
	}

	/**
	 * Get the user's friends based on their local user ID.
	 *
	 * @param  Parser|null &$parser - Not used, but the parser will pass it regardless.
	 * @param  integer     $userId  Local User ID
	 * @return array	Parser compatible HTML array.
	 */
	public static function friendList(?Parser &$parser = null, int $userId) {
		$user = User::newFromId($userId);

		if (!$user) {
			return 0;
		}

		$friendship = new Friendship($user);
		$friends = $friendship->getFriends();
		if (count($friends) == 0) {
			return '';
		}

		return [
			self::listFromArray($friends, false, 10, 0, true),
			'isHTML' => true,
		];
	}

	/**
	 * Creates a UL html list from an array of user IDs. The callback function can insert extra html in the LI tags.
	 *
	 * @param  array   $userIds        [Optional] User IDs
	 * @param  bool    $manageButtons  [Optional] signature: callback($userObj) returns string
	 * @param  integer $limit          [Optional] Number of results to limit.
	 * @param  integer $offset         [Optional] Offset to start from.
	 * @param  bool    $sortByActivity [Optional] Sort by user activity instead of name.
	 * @return string	HTML UL List
	 */
	public static function listFromArray($userIds = [], $manageButtons = false, $limit = 10, $offset = 0, $sortByActivity = false) {
		$html = '
		<ul class="friends">';
		foreach ($userIds as $userId) {
			$fUser = User::newFromId($userId);
			if (!$fUser->getId()) {
				// Just silently drop if the user is actually missing.
				continue;
			}
			$html .= '<li>';
			$html .= ProfilePage::userAvatar(null, 32, $fUser->getEmail(), $fUser->getName())[0];
			$html .= ' ' . CP::userLink($fUser->getId());
			if ($manageButtons) {
				$html .= ' ' . self::addFriendButton($fUser->getId());
			}
			$html .= '</li>';
		}
		$html .= '
		</ul>';

		return $html;
	}
}

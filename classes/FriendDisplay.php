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
 */

namespace CurseProfile;

use Html;
use MediaWiki\MediaWikiServices;
use Parser;
use User;

/**
 * A class to manage displaying a list of friends on a user profile
 */
class FriendDisplay {
	/**
	 * Generates an array to be inserted into the nav links of the page
	 *
	 * @param int $userId User ID of the profile page being viewed
	 * @param User $actor The User performing friend management actions.
	 * @param array &$links reference to the links array into which the links will be inserted
	 *
	 * @return void
	 */
	public static function addFriendLink( User $toUser, User $actor, array &$links ) {
		if ( $actor->isAnon() || $toUser->isAnon() ) {
			return;
		}

		$friendship = new Friendship( $actor );
		$userId = $toUser->getId();

		$links['user_id'] = $toUser->getId();
		$relationship = $friendship->getRelationship( $toUser );

		switch ( $relationship ) {
			case Friendship::STRANGERS:
				$links['views']['add_friend'] = [
					'action'  => 'send',
					'class'   => false,
					'href'    => "/Special:AddFriend/$userId",
					'text'    => wfMessage( 'friendrequestsend' )->plain(),
				];
				break;

			case Friendship::REQUEST_SENT:
				$links['views']['add_friend'] = [
					'action'  => 'remove',
					'class'   => 'friend-request-sent',
					'href'    => "/Special:RemoveFriend/$userId",
					'text'    => wfMessage( 'friendrequestcancel' )->plain(),
				];
				break;

			case Friendship::REQUEST_RECEIVED:
				$links['actions']['add_friend'] = [
					'action'  => 'confirm',
					'class'   => 'friend-request-confirm',
					'href'    => "/Special:ConfirmFriend/$userId",
					'text'    => wfMessage( 'confirmfriend-response' )->plain(),
				];
				$links['actions']['ignore_friend'] = [
					'action'  => 'ignore',
					'class'   => 'friend-request-ignore',
					'href'    => "/Special:ConfirmFriend/$userId",
					'text'    => wfMessage( 'ignorefriend-response' )->plain(),
				];
				break;

			case Friendship::FRIENDS:
				$links['views']['remove_friend'] = [
					'action'  => 'remove',
					'class'   => 'friend-request-sent',
					'href'    => "/Special:RemoveFriend/$userId",
					'text'    => wfMessage( 'removefriend' )->plain(),
					'confirm' => wfMessage( 'friendrequestremove-prompt', $toUser->getName() )->plain(),
				];
				break;

			default:
				return;
		}
	}

	/**
	 * Friend Button Stuff
	 *
	 * @param User $toUser User ID of the user on which the buttons will act
	 * @param User $actor The User performing friend management actions.
	 *
	 * @return string HTML button stuff
	 */
	public static function friendButtons( User $toUser, User $actor ) {
		// reuse logic from the other function
		$links = [];
		self::addFriendLink( $toUser, $actor, $links );

		if ( isset( $links['actions'] ) ) {
			$links['views'] = $links['actions'];
		}

		$html = '';

		if ( isset( $links['views'] ) && count( $links['views'] ) ) {
			foreach ( $links['views'] as $link ) {
				$attribs = [
					'class' => 'friendship-action wds-button wds-is-secondary',
					'data-action' => $link['action'],
					'data-id' => $links['user_id'],
				];
				if ( isset( $link['confirm'] ) ) {
					$attribs['data-confirm'] = $link['confirm'];
				}
				$html .= Html::rawElement( 'button', $attribs, $link['text'] );
			}
		}

		return $html;
	}

	/**
	 * Adds a Friend Button
	 *
	 * @param User $toUser
	 * @param User $actor The User performing friend management actions.
	 *
	 * @return string
	 */
	public static function addFriendButton( User $toUser, User $actor ) {
		return '<div class="friendship-container">' . self::friendButtons( $toUser, $actor ) . '</div>';
	}

	/**
	 * Get the user's friend count based on their local user ID.
	 *
	 * @param Parser|null $parser - Not used, but the parser will pass it regardless.
	 * @param int $userId Local User ID
	 *
	 * @return int Number of friends.
	 */
	public static function count( ?Parser $parser = null, int $userId ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$user = $userFactory->newFromId( $userId );

		if ( !$user ) {
			return 0;
		}

		$friendship = new Friendship( $user );
		$friendTypes = $friendship->getFriends();

		return count( $friendTypes['friends'] );
	}

	/**
	 * Get the user's friends based on their local user ID.
	 *
	 * @param Parser|null &$parser Not used, but the parser will pass it regardless.
	 * @param int $userId Local User ID
	 *
	 * @return array Parser compatible HTML array.
	 */
	public static function friendList( ?Parser &$parser = null, int $userId ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$user = $userFactory->newFromId( $userId );

		if ( !$user ) {
			return 0;
		}

		$friendship = new Friendship( $user );
		$friendTypes = $friendship->getFriends();
		if ( !count( $friendTypes['friends'] ) ) {
			return '';
		}

		return [
			self::listFromArray( $friendTypes['friends'], false, null, 10, 0, true ),
			'isHTML' => true,
		];
	}

	/**
	 * Creates a UL html list from an array of user IDs. The callback function can insert extra html in the LI tags.
	 *
	 * @param array $user [Optional] User objects
	 * @param bool $manageButtons [Optional] signature: callback($userObj) returns string
	 * @param User|null $actor [Optional] The User performing friend management actions.
	 * @param int $limit [Optional] Number of results to limit.
	 * @param int $offset [Optional] Offset to start from.
	 * @param bool $sortByActivity [Optional] Sort by user activity instead of name.
	 *
	 * @return string HTML UL List
	 */
	public static function listFromArray( ?array $users = [], $manageButtons = false, ?User $actor = null, $limit = 10, $offset = 0, $sortByActivity = false ) {
		if ( $limit > 0 || $offset > 0 ) {
			$users = array_slice( $users, $offset, $limit, true );
		}

		$html = '
		<ul class="friends">';
		foreach ( $users as $fUser ) {
			if ( !$fUser->getId() ) {
				// Just silently drop if the user is actually missing.
				continue;
			}
			$html .= '<li>';
			$html .= ProfilePage::userAvatar( null, 32, $fUser->getEmail(), $fUser->getName() )[0];
			$html .= ' ' . CP::userLink( $fUser->getId() );
			if ( $manageButtons && $actor !== null ) {
				$html .= ' ' . self::addFriendButton( $fUser, $actor );
			}
			$html .= '</li>';
		}
		$html .= '
		</ul>';

		return $html;
	}
}

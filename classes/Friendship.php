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

use Cheevos\Cheevos;
use Cheevos\CheevosException;
use Hooks;
use MWException;
use Reverb\Notification\NotificationBroadcast;
use SpecialPage;
use Title;
use User;

/**
 * Class that manages friendship relations between users. Create an instance with a User object.
 * All relationships statuses are then described from the perspective of that user.
 */
class Friendship {
	private $user;

	/**
	 * Relationship status constants
	 */
	const STRANGERS        = 1;
	const FRIENDS          = 2;
	const REQUEST_SENT     = 3;
	const REQUEST_RECEIVED = 4;

	/**
	 * The user passed to the constructor is used as the main user from which the
	 * perspective of the SENT/RECEIVED status are determined.
	 *
	 * @param User $user
	 */
	public function __construct(User $user) {
		if (!$user->getId()) {
			throw new MWException('Anonymous user object passed.');
		}
		$this->user = $user;
	}

	/**
	 * Check the relationship status between two users.
	 *
	 * @param  integer $toUserId user ID of a user
	 * @return int	-1 on failure or one of the class constants STRANGERS, FRIENDS, REQUEST_SENT, REQUEST_RECEIVED
	 */
	public function getRelationship($toUserId) {
		try {
			$status = Cheevos::getFriendStatus($this->user->getId(), $toUserId);
			if ($status['status']) {
				return $status['status'];
			}
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
		}
		return -1;
	}

	/**
	 * Returns the array of user IDs for this or another user's friends
	 *
	 * @return array User IDs of friends
	 */
	public function getFriends() {
		try {
			$friends = Cheevos::getFriends($this->user->getId());
			if ($friends['friends']) {
				return $friends['friends'];
			}
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
		}
		return [];
	}

	/**
	 * Returns the number of friends a user has
	 *
	 * @return integer Number of friends
	 */
	public function getFriendCount() {
		// my god look how efficient this is
		$friends = $this->getFriends();
		return count($friends);
	}

	/**
	 * Returns the array of pending friend requests that have sent this user
	 *
	 * @return array Keys are user IDs of potential friends,
	 *     values are json strings with additional data (currently empty)
	 */
	public function getReceivedRequests() {
		try {
			$friends = Cheevos::getFriends($this->user->getId());
			if ($friends['incoming_requests']) {
				return $friends['incoming_requests'];
			}
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
		}
		return [];
	}

	/**
	 * Returns the array of pending friend requests that have been sent by this user
	 *
	 * @return array Values are user IDs
	 */
	public function getSentRequests() {
		try {
			$friends = Cheevos::getFriends($this->user->getId());
			if ($friends['outgoing_requests']) {
				return $friends['outgoing_requests'];
			}
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
		}
		return [];
	}

	/**
	 * Sends a friend request to a given user
	 *
	 * @param integer $toUserId User ID of a user.
	 *
	 * @return boolean True on success, False on failure.
	 */
	public function sendRequest(int $toUserId) {
		$toUser = User::newFromId($toUserId);

		if (!$toUser || $toUser->isAnon() || $this->user->getId() === $toUserId) {
			return false;
		}

		if ($this->user->isBlocked() || $toUser->isBlocked()) {
			return ['error' => 'friendrequest-blocked'];
		}

		$relationShip = $this->getRelationship($toUserId);

		if ($relationShip == -1) {
			return ['error' => 'friendrequest-status-unavailable'];
		}

		if ($relationShip !== self::STRANGERS) {
			return ['error' => 'friendrequest-already-friends'];
		}

		try {
			$makeFriend = Cheevos::createFriendRequest($this->user->getId(), $toUserId);
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
			return false;
		}

		$fromUserTitle = Title::makeTitle(NS_USER_PROFILE, $this->user->getName());
		$canonicalUrl = SpecialPage::getTitleFor('ManageFriends')->getFullURL();
		$broadcast = NotificationBroadcast::newSingle(
			'user-interest-profile-friendship',
			$this->user,
			$toUser,
			[
				'url' => $canonicalUrl,
				'message' => [
					[
						'user_note',
						''
					],
					[
						1,
						$this->user->getName()
					],
					[
						2,
						$fromUserTitle->getFullURL()
					],
					[
						3,
						$canonicalUrl
					]
				]
			]
		);
		if ($broadcast) {
			$broadcast->transmit();
		}

		Hooks::run('CurseProfileAddFriend', [$this->user, $toUser]);
		return true;
	}

	/**
	 * Accepts a pending request.
	 *
	 * @param integer $toUserId User ID of a user.
	 *
	 * @return boolean True on success, False on failure.
	 */
	public function acceptRequest(int $toUserId) {
		if ($this->user->getId() === $toUserId || $toUserId < 1) {
			return false;
		}

		try {
			$res = Cheevos::acceptFriendRequest($this->user->getId(), $toUserId);
			if ($res['message'] == "success") {
				return true;
			}
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
		}
		return false;
	}

	/**
	 * Ignores and dismisses a pending request.
	 *
	 * @param integer $toUserId User ID of a user.
	 *
	 * @return boolean True on success, False on failure.
	 */
	public function ignoreRequest(int $toUserId) {
		if ($this->user->getId() === $toUserId || $toUserId < 1) {
			return false;
		}

		try {
			$res = Cheevos::cancelFriendRequest($this->user->getId(), $toUserId);
			if ($res['message'] == "success") {
				return true;
			}
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
		}
		return false;
	}

	/**
	 * Removes a friend relationship or cancels a pending request.
	 *
	 * @param integer $toUserId User ID of a user.
	 *
	 * @return boolean True on success, False on failure.
	 */
	public function removeFriend(int $toUserId) {
		if ($this->user->getId() === $toUserId || $toUserId < 1) {
			return false;
		}

		try {
			Cheevos::cancelFriendRequest($this->user->getId(), $toUserId);
		} catch (CheevosException $e) {
			wfDebug(__METHOD__ . ": Caught CheevosException - " . $e->getMessage());
			return false;
		}

		Hooks::run('CurseProfileRemoveFriend', [$this->user->getId(), $toUserId]);

		return true;
	}
}

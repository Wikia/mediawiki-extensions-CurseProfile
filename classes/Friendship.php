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
 * Class that manages friendship relations between users. Create an instance with a curse ID.
 * All relationships statuses are then described from the perspective of that user.
 */
class Friendship {
	private $globalId;

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
	 * @param	integer	curse ID of a user
	 */
	public function __construct($globalId) {
		$this->globalId = intval($globalId);
	}

	/**
	 * Check the relationship status between two users.
	 *
	 * @param	integer	curse ID of a user
	 * @return	integer	-1 on failure or one of the class constants STRANGERS, FRIENDS, REQUEST_SENT, REQUEST_RECEIVED
	 */
	public function getRelationship($toUser) {
		if ($this->globalId < 1) {
			return -1;
		}
		try {
			$status = \Cheevos\Cheevos::getFriendStatus($this->globalId, $toUser);
			if ($status['status']) {
				return $status['status'];
			}
		} catch (\Cheevos\CheevosException $e) {
			// do nothing with this. No one care. Cheevos Down.
		}
		return -1;
	}

	/**
	 * Returns the array of curse IDs for this or another user's friends
	 *
	 * @param	integer	optional curse ID of a user (default $this->globalId)
	 * @return	array	curse IDs of friends
	 */
	public function getFriends($user = null) {
		if ($this->globalId < 1) {
			return [];
		}

		if ($user == null) {
			$user = $this->globalId;
		}

		try {
			$friends = \Cheevos\Cheevos::getFriends($user);
			if ($friends['friends']) {
				return $friends['friends'];
			}
		} catch (\Cheevos\CheevosException $e) {
			// do nothing with this. No one cares. Cheevos Down.
		}
		return [];
	}

	/**
	 * Returns the number of friends a user has
	 *
	 * @param	integer	optional curse ID of a user (default $this->globalId)
	 * @return	integer	a number of friends
	 */
	public function getFriendCount($user = null) {
		// my god look how efficiant this is
		$friends = $this->getFriends($user);
		return count($friends);
	}

	/**
	 * Returns the array of pending friend requests that have sent this user
	 *
	 * @return	array	keys are curse IDs of potential friends,
	 *     values are json strings with additional data (currently empty)
	 */
	public function getReceivedRequests() {
		if ($this->globalId < 1) {
			return [];
		}
		try {
			$friends = \Cheevos\Cheevos::getFriends($this->globalId);
			if ($friends['incoming_requests']) {
				return $friends['incoming_requests'];
			}
		} catch (\Cheevos\CheevosException $e) {
			// do nothing with this. No one cares. Cheevos Down.
		}
		return [];
	}

	/**
	 * Returns the array of pending friend requests that have been sent by this user
	 *
	 * @return	array	values are curse IDs
	 */
	public function getSentRequests() {
		if ($this->globalId < 1) {
			return [];
		}
		try {
			$friends = \Cheevos\Cheevos::getFriends($this->globalId);
			if ($friends['outgoing_requests']) {
				return $friends['outgoing_requests'];
			}
		} catch (\Cheevos\CheevosException $e) {
			// do nothing with this. No one cares. Cheevos Down.
		}
		return [];
	}

	/**
	 * Sends a friend request to a given user
	 *
	 * @access	public
	 * @param	integer	Global ID of the user to friend.
	 * @return	boolean	True on success, False on failure.
	 */
	public function sendRequest($toGlobalId) {
		global $wgUser;

		if ($wgUser->isBlocked()) {
			return false;
		}

		try {
			$makeFriend = \Cheevos\Cheevos::createFriendRequest($this->globalId, $toGlobalId);
		} catch (\Cheevos\CheevosException $e) {
			return false;
		}

		if ($this->getRelationship($toGlobalId) != self::STRANGERS) {
			return false;
		}

		$toGlobalId = intval($toGlobalId);
		$lookup = \CentralIdLookup::factory();
		$toLocalUser = $lookup->localUserFromCentralId($toGlobalId);

		if ($this->globalId < 1 || $this->globalId == $toGlobalId || $toGlobalId < 1 || !$toLocalUser->getId()) {
			return false;
		}

		\EchoEvent::create([
			'type' => 'friendship',
			'agent' => $wgUser,
			'title' => $wgUser->getUserPage(),
			'extra' => [
				'user' => $toLocalUser,
				'target_user_id' => $toLocalUser->getId()
			]
		]);

		wfRunHooks('CurseProfileAddFriend', [$wgUser, $toLocalUser]);
		return true;
	}

	/**
	 * Accepts a pending request
	 *
	 * @access	public
	 * @param	integer	curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function acceptRequest($toGlobalId) {
		if ($this->globalId < 1 || $this->globalId == $toGlobalId || $toGlobalId < 1) {
			return -1;
		}
		try {
			$res = \Cheevos\Cheevos::acceptFriendRequest($this->globalId, $toGlobalId);
			if ($res['message'] == "success") {
				return true;
			}
		} catch (\Cheevos\CheevosException $e) {
			// do nothing with this. No one cares. Cheevos Down.
		}
		return false;
	}

	/**
	 * Ignores and dismisses a pending request
	 *
	 * @access	public
	 * @param	integer	curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function ignoreRequest($toGlobalId) {
		if ($this->globalId < 1 || $this->globalId == $toGlobalId || $toGlobalId < 1) {
			return -1;
		}
		try {
			$res = \Cheevos\Cheevos::cancelFriendRequest($this->globalId, $toGlobalId);
			if ($res['message'] == "success") {
				return true;
			}
		} catch (\Cheevos\CheevosException $e) {
			// do nothing with this. No one cares. Cheevos Down.
		}
		return false;
	}

	/**
	 * Removes a friend relationship, or cancels a pending request
	 *
	 * @param	integer	global ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function removeFriend($toUser) {
		$toUser = intval($toUser);
		if ($this->globalId < 1 || $this->globalId == $toUser || $toUser < 1) {
			return false;
		}

		/*
			// NOTE: The documentation of this function suggests this may be a local id and not a global id.
			// Uncomment this to fix that issue if we find it in testing.

			$localUser = \User::newFromId($toUser);
			$lookup = \CentralIdLookup::factory();
			$globalId = $lookup->centralIdFromLocalUser($localUser);

			// Otherwise, the below code handles it just fine.
		*/
		$globalId = $toUser;

		try {
			\Cheevos\Cheevos::cancelFriendRequest($this->globalId, $globalId);
		} catch (\Cheevos\CheevosException $e) {
			return false;
		}

		wfRunHooks('CurseProfileRemoveFriend', [$this->globalId, $toUser]);

		return true;
	}
}

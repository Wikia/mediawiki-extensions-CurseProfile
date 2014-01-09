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
	private $curse_id;

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
	 * @param	int		curse ID of a user
	 */
	public function __construct($curse_id) {
		$this->curse_id = intval($curse_id);
		if ($this->curse_id < 1) {
			throw new \Exception('Invalid Curse ID');
		}
	}

	/**
	 * Check the relationship status between two users.
	 *
	 * @param	int		curse ID of a user
	 * @return	int		-1 on failure or one of the class constants STRANGERS, FRIENDS, REQUEST_SENT, REQUEST_RECEIVED
	 */
	public function getRelationship($toUser) {
		$toUser = intval($toUser);
		if ($this->curse_id == $toUser || $toUser < 1) {
			return -1;
		}

		$mouse = CP::loadMouse();

		// first check for existing friends
		if ($mouse->redis->sismember($this->friendListRedisKey(), $toUser)) {
			return self::FRIENDS;
		}

		// check for pending requests
		if ($mouse->redis->hexists($this->requestsRedisKey(), $toUser)) {
			return self::REQUEST_RECEIVED;
		}
		if ($mouse->redis->hexists($this->requestsRedisKey($toUser), $this->curse_id)) {
			return self::REQUEST_SENT;
		}

		return self::STRANGERS; // assumption when not found in redis
	}

	/**
	 * Returns the array of curse IDs for the current user's friends
	 *
	 * @param	int		optional curse ID of a user (default $this->curse_id)
	 * @return	array	curse IDs of friends
	 */
	public function getFriends($user = null) {
		if ($user == null) {
			$user = $this->curse_id;
		}
		$mouse = CP::loadMouse();
		return $mouse->redis->smembers($this->friendListRedisKey($user));
	}

	/**
	 * Generates a redis key for the hash of pending requests
	 *
	 * @param	int		optional curse ID of a user (default $this->curse_id)
	 * @return	string	redis key to be used
	 */
	private function requestsRedisKey($user = null) {
		if ($user == null) {
			$user = $this->curse_id;
		}
		return 'requests:'.$user;
	}

	/**
	 * Generates a redis key for a set of friends
	 *
	 * @param	int		optional curse ID of a user (default $this->curse_id)
	 * @return	string	redis key to be used
	 */
	private function friendListRedisKey($user = null) {
		if ($user == null) {
			$user = $this->curse_id;
		}
		return 'friendlist:'.$user;
	}

	/**
	 * Sends a friend request to a given user
	 *
	 * @param	int		curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function sendRequest($toUser) {
		$toUser = intval($toUser);
		if ($this->curse_id == $toUser || $toUser < 1) {
			return false;
		}

		if ($this->getRelationship($toUser) != self::STRANGERS) {
			return false;
		}

		$mouse = CP::loadMouse();
		$mouse->redis->hset($this->requestsRedisKey($toUser), $this->curse_id, '{}');

		// TODO add sync

		return true;
	}

	/**
	 * Accepts a pending request
	 *
	 * @param	int		curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function acceptRequest($toUser) {
		return $this->respondToRequest($toUser, 'accept');
	}

	/**
	 * Ignores and dismisses a pending request
	 *
	 * @param	int		curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function ignoreRequest($toUser) {
		return $this->respondToRequest($toUser, 'ignore');
	}

	/**
	 * Shared logic between accepting and ignoring pending requests
	 *
	 * @param	int		user id of whose request is being responded to
	 * @param	string	responce being sent. one of 'accept' or 'ignore'
	 * @return	bool	true on success
	 */
	private function respondToRequest($toUser, $response) {
		$toUser = intval($toUser);
		if ($this->curse_id == $toUser || $toUser < 1) {
			return -1;
		}

		if ($this->getRelationship($toUser) != self::REQUEST_RECEIVED) {
			return false;
		}

		$mouse = CP::loadMouse();

		// delete pending request
		$mouse->redis->hdel($this->requestsRedisKey(), $toUser);

		if ($response == 'accept') {
			// add reciprocal friendship
			$mouse->redis->sadd($this->friendListRedisKey(), $toUser);
			$mouse->redis->sadd($this->friendListRedisKey($toUser), $this->curse_id);
		}

		// TODO add sync

		return true;
	}

	/**
	 * Removes a friend relationship
	 *
	 * @param	int		curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function removeFriend($toUser) {
		$toUser = intval($toUser);
		if ($this->curse_id == $toUser || $toUser < 1) {
			return -1;
		}

		if ($this->getRelationship($toUser) != self::FRIENDS) {
			return false;
		}

		$mouse = CP::loadMouse();

		$mouse->redis->srem($this->friendListRedisKey(), $toUser);
		$mouse->redis->srem($this->friendListRedisKey($toUser), $this->curse_id);

		// TODO add sync

		return true;
	}
}

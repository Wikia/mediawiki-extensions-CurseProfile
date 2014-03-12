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
	}

	/**
	 * Check the relationship status between two users.
	 *
	 * @param	int		curse ID of a user
	 * @return	int		-1 on failure or one of the class constants STRANGERS, FRIENDS, REQUEST_SENT, REQUEST_RECEIVED
	 */
	public function getRelationship($toUser) {
		$toUser = intval($toUser);
		if ($this->curse_id < 1 || $this->curse_id == $toUser || $toUser < 1) {
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
	 * Returns the array of curse IDs for this or another user's friends
	 *
	 * @param	int		optional curse ID of a user (default $this->curse_id)
	 * @return	array	curse IDs of friends
	 */
	public function getFriends($user = null) {
		if ($this->curse_id < 1) {
			return [];
		}

		if ($user == null) {
			$user = $this->curse_id;
		}
		$mouse = CP::loadMouse();
		return $mouse->redis->smembers($this->friendListRedisKey($user));
	}

	/**
	 * Returns the array of pending friend requests that have sent this user
	 *
	 * @return	array	keys are curse IDs of potential friends,
	 *     values are json strings with additional data (currently empty)
	 */
	public function getReceivedRequests() {
		if ($this->curse_id < 1) {
			return [];
		}

		$mouse = CP::loadMouse();
		return $mouse->redis->hgetall($this->requestsRedisKey());
	}

	/**
	 * Returns the array of pending friend requests that have been sent by this user
	 *
	 * @return	array	values are curse IDs
	 */
	public function getSentRequests() {
		if ($this->curse_id < 1) {
			return [];
		}

		$mouse = CP::loadMouse();
		return $mouse->redis->smembers($this->sentRequestsRedisKey());
	}

	/**
	 * Generates a redis key for the hash of pending requests received
	 *
	 * @param	int		optional curse ID of a user (default $this->curse_id)
	 * @return	string	redis key to be used
	 */
	private function requestsRedisKey($user = null) {
		if ($user == null) {
			$user = $this->curse_id;
		}
		return 'friendrequests:'.$user;
	}

	/**
	 * Generates a redis key for the set of pending requests sent
	 *
	 * @param	int		optional curse ID of a user (default $this->curse_id)
	 * @return	string	redis key to be used
	 */
	private function sentRequestsRedisKey($user = null) {
		if ($user == null) {
			$user = $this->curse_id;
		}
		return 'friendrequests:'.$user.':sent';
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
		if ($this->curse_id < 1 || $this->curse_id == $toUser || $toUser < 1) {
			return false;
		}

		// Queue sync before error check in case redis is not in sync
		FriendSync::queue([
			'task' => 'add',
			'actor' => $this->curse_id,
			'target' => $toUser,
		]);
		if ($this->getRelationship($toUser) != self::STRANGERS) {
			return false;
		}

		$mouse = CP::loadMouse();
		$mouse->redis->hset($this->requestsRedisKey($toUser), $this->curse_id, '{}');
		$mouse->redis->sadd($this->sentRequestsRedisKey(), $toUser);

		$user = \User::newFromId(CP::userIDfromCurseID($toUser));
		if ($user->getEmail() && $user->getIntOption('friendreqemail')) {
			if (trim($user->getRealName())) {
				$name = $user->getRealName();
			} else {
				$name = $user->getName();
			}
			$updatePrefsLink = \SpecialPage::getTitleFor('Preferences');
			$thisUser = \User::newFromId(CP::userIDfromCurseID($this->curse_id));
			$subject = wfMessage('friendreqemail-subj', $thisUser->getName())->parse();
			$body = wfMessage('friendreqemail-body')->params(
					$name,
					$thisUser->getName(),
					$thisUser->getUserPage()->getFullURL(),
					$updatePrefsLink->getFullURL().'#mw-prefsection-personal-email'
				)->parse();
			$user->sendMail($subject, $body);
		}

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
		if ($this->curse_id < 1 || $this->curse_id == $toUser || $toUser < 1) {
			return -1;
		}

		FriendSync::queue([
			'task' => ($response == 'accept' ? 'confirm' : 'ignore'),
			'actor' => $this->curse_id,
			'target' => $toUser
		]);
		if ($this->getRelationship($toUser) != self::REQUEST_RECEIVED) {
			return false;
		}

		$mouse = CP::loadMouse();

		// delete pending request
		$mouse->redis->hdel($this->requestsRedisKey(), $toUser);
		$mouse->redis->srem($this->sentRequestsRedisKey($toUser), $this->curse_id);

		if ($response == 'accept') {
			// add reciprocal friendship
			$mouse->redis->sadd($this->friendListRedisKey(), $toUser);
			$mouse->redis->sadd($this->friendListRedisKey($toUser), $this->curse_id);
		}

		return true;
	}

	/**
	 * Removes a friend relationship, or cancels a pending request
	 *
	 * @param	int		curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function removeFriend($toUser) {
		$toUser = intval($toUser);
		if ($this->curse_id < 1 || $this->curse_id == $toUser || $toUser < 1) {
			return false;
		}

		FriendSync::queue([
			'task' => 'remove',
			'actor' => $this->curse_id,
			'target' => $toUser
		]);

		$mouse = CP::loadMouse();

		$mouse->redis->hdel($this->requestsRedisKey($toUser), $this->curse_id);
		$mouse->redis->srem($this->sentRequestsRedisKey($toUser), $this->curse_id);
		$mouse->redis->srem($this->friendListRedisKey(), $toUser);
		$mouse->redis->srem($this->friendListRedisKey($toUser), $this->curse_id);

		return true;
	}

	/**
	 * Treats the current database info as authoritative and corrects redis to match
	 * TODO, currently incomplete implementation
	 *
	 * @return	null
	 */
	public function syncToRedis() {
		if (!defined('CURSEPROFILE_MASTER')) {
			return;
		}
		$mouse = CP::loadMouse();

		$res = $mouse->DB->select([
			'select' => 'ur.*',
			'from'   => ['user_relationship' => 'ur'],
			'where'  => "ur.r_user_id = {$this->curse_id} AND ur.r_type = 1",
		]);
		while ($friend = $mouse->DB->fetch($res)) {
			$mouse->redis->sadd($this->friendListRedisKey(), $friend['r_user_id_relation']);
			$mouse->redis->sadd($this->friendListRedisKey($friend['r_user_id_relation']), $this->curse_id);
		}

		$res = $mouse->DB->select([
			'select' => 'ur.*',
			'from'   => ['user_relationship_request' => 'ur'],
			'where'  => "ur.ur_user_id = {$this->curse_id} AND ur.ur_type = 1",
		]);
		while ($friend = $mouse->DB->fetch($res)) {
			$mouse->redis->sadd($this->friendListRedisKey(), $friend['r_user_id_relation']);
			$mouse->redis->sadd($this->friendListRedisKey($friend['r_user_id_relation']), $this->curse_id);
		}
	}

	/**
	 * This will write a given change to the database
	 */
	public function saveToDB($args) {
		if (!defined('CURSEPROFILE_MASTER')) {
			return 1; // the appropriate tables don't exist here
		}
		$args['target'] = intval($args['target']);
		if ($args['target'] < 1) {
			return 1;
		}

		$mouse = CP::loadMouse();
		switch ($args['task']) {
			case 'add':
				$mouse->DB->insert('user_relationship_request', [
					'ur_user_id_from' => $this->curse_id,
					'ur_user_id_to'   => $args['target'],
					'ur_type'         => 1,
					'ur_date'         => date( 'Y-m-d H:i:s' ),
				]);
				wfRunHooks('CurseProfileAddFriend', [$this->curse_id, $args['target']]);
				break;

			case 'confirm':
				$mouse->DB->insert('user_relationship', [
					'r_user_id'          => $this->curse_id,
					'r_user_id_relation' => $args['target'],
					'r_type'             => 1,
					'r_date'             => date( 'Y-m-d H:i:s' ),
				]);
				$mouse->DB->insert('user_relationship', [
					'r_user_id'          => $args['target'],
					'r_user_id_relation' => $this->curse_id,
					'r_type'             => 1,
					'r_date'             => date( 'Y-m-d H:i:s' ),
				]);
				// intentional fall-through

			case 'ignore':
				$mouse->DB->delete('user_relationship_request', "ur_user_id_from = {$args['target']} AND ur_user_id_to = {$this->curse_id}");
				break;

			case 'remove':
				$mouse->DB->delete('user_relationship', "r_user_id = {$args['target']} AND r_user_id_relation = {$this->curse_id}");
				$mouse->DB->delete('user_relationship', "r_user_id = {$this->curse_id} AND r_user_id_relation = {$args['target']}");
				$mouse->DB->delete('user_relationship_request', "ur_user_id_from = {$this->curse_id} AND ur_user_id_to = {$args['target']}");
				wfRunHooks('CurseProfileRemoveFriend', [$this->curse_id, $args['target']]);
				break;

			default:
				return 1;
		}
		return 0;
	}
}

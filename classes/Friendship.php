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
	 * @param	integer	curse ID of a user
	 */
	public function __construct($curse_id) {
		$this->curse_id = intval($curse_id);
	}

	/**
	 * Check the relationship status between two users.
	 *
	 * @param	integer	curse ID of a user
	 * @return	integer	-1 on failure or one of the class constants STRANGERS, FRIENDS, REQUEST_SENT, REQUEST_RECEIVED
	 */
	public function getRelationship($toUser) {
		$toUser = intval($toUser);
		if ($this->curse_id < 1 || $this->curse_id == $toUser || $toUser < 1) {
			return -1;
		}

		$redis = \RedisCache::getMaster();

		// first check for existing friends
		if ($redis->sIsMember($this->friendListRedisKey(), $toUser)) {
			return self::FRIENDS;
		}

		// check for pending requests
		if ($redis->hExists($this->requestsRedisKey(), $toUser)) {
			return self::REQUEST_RECEIVED;
		}
		if ($redis->hExists($this->requestsRedisKey($toUser), $this->curse_id)) {
			return self::REQUEST_SENT;
		}

		return self::STRANGERS; // assumption when not found in redis
	}

	/**
	 * Returns the array of curse IDs for this or another user's friends
	 *
	 * @param	integer	optional curse ID of a user (default $this->curse_id)
	 * @return	array	curse IDs of friends
	 */
	public function getFriends($user = null) {
		if ($this->curse_id < 1) {
			return [];
		}

		if ($user == null) {
			$user = $this->curse_id;
		}
		$redis = \RedisCache::getMaster();
		return $redis->sMembers($this->friendListRedisKey($user));
	}

	/**
	 * Returns the number of friends a user has
	 *
	 * @param	integer	optional curse ID of a user (default $this->curse_id)
	 * @return	integer	a number of friends
	 */
	public function getFriendCount($user = null) {
		if ($this->curse_id < 1) {
			return [];
		}

		if ($user == null) {
			$user = $this->curse_id;
		}
		$redis = \RedisCache::getMaster();
		return $redis->sCard($this->friendListRedisKey($user));
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

		$redis = \RedisCache::getMaster();
		return $redis->hGetAll($this->requestsRedisKey());
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

		$redis = \RedisCache::getMaster();
		return $redis->sMembers($this->sentRequestsRedisKey());
	}

	/**
	 * Generates a redis key for the hash of pending requests received
	 *
	 * @param	integer	optional curse ID of a user (default $this->curse_id)
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
	 * @param	integer	optional curse ID of a user (default $this->curse_id)
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
	 * @param	integer	optional curse ID of a user (default $this->curse_id)
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
	 * @param	integer	curse ID of a user
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

		$redis = \RedisCache::getMaster();
		$redis->hSet($this->requestsRedisKey($toUser), $this->curse_id, '{}');
		$redis->sAdd($this->sentRequestsRedisKey(), $toUser);

		global $wgUser;
		\EchoEvent::create([
			'type' => 'friendship-request',
			'agent' => $wgUser,
			// 'title' => $wgUser->getUserPage(),
			'extra' => [
				'target_user_id' => CP::userIDfromCurseID($toUser)
			]
		]);

		wfRunHooks('CurseProfileAddFriend', [$this->curse_id, $toUser]);

		return true;
	}

	/**
	 * Accepts a pending request
	 *
	 * @param	integer	curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function acceptRequest($toUser) {
		return $this->respondToRequest($toUser, 'accept');
	}

	/**
	 * Ignores and dismisses a pending request
	 *
	 * @param	integer	curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function ignoreRequest($toUser) {
		return $this->respondToRequest($toUser, 'ignore');
	}

	/**
	 * Shared logic between accepting and ignoring pending requests
	 *
	 * @param	integer	user id of whose request is being responded to
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

		$redis = \RedisCache::getMaster();

		// delete pending request
		$redis->hDel($this->requestsRedisKey(), $toUser);
		$redis->sRem($this->sentRequestsRedisKey($toUser), $this->curse_id);

		if ($response == 'accept') {
			// add reciprocal friendship
			$redis->sAdd($this->friendListRedisKey(), $toUser);
			$redis->sAdd($this->friendListRedisKey($toUser), $this->curse_id);
		}

		return true;
	}

	/**
	 * Removes a friend relationship, or cancels a pending request
	 *
	 * @param	integer	curse ID of a user
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

		$redis = \RedisCache::getMaster();

		// remove pending incoming requests
		$redis->hDel($this->requestsRedisKey($toUser), $this->curse_id);
		$redis->hDel($this->requestsRedisKey(), $toUser);

		// remove sent request references
		$redis->sRem($this->sentRequestsRedisKey($toUser), $this->curse_id);
		$redis->sRem($this->sentRequestsRedisKey(), $toUser);

		// remove existing friendship
		$redis->sRem($this->friendListRedisKey($toUser), $this->curse_id);
		$redis->sRem($this->friendListRedisKey(), $toUser);

		wfRunHooks('CurseProfileRemoveFriend', [$this->curse_id, $toUser]);

		return true;
	}

	/**
	 * Treats the current database info as authoritative and corrects redis to match
	 * If instance of Friendship was created with a null curse ID, will sync entire table
	 *
	 * @param	ILogger	instance of a logger if output is desired
	 * @return	null
	 */
	public function syncToRedis(\SyncService\ILogger $logger = null) {
		if (!defined('CURSEPROFILE_MASTER')) {
			return;
		}
		if (is_null($logger)) {
			$log = function($str, $time=null) {};
		} else {
			$log = function($str, $time=null) use ($logger) {
				$logger->outputLine($str, $time);
			};
		}
		$redis = \RedisCache::getMaster();
		$db = CP::getDb(DB_MASTER);

		$where = ['r_type' => 1];
		if ($this->curse_id > 0) {
			$where[] = "(r_user_id = ".$db->addQuotes($this->curse_id)." OR r_user_id_relation = ".$db->addQuotes($this->curse_id);
		}
		$results = $db->select(
			['user_relationship'],
			['*'],
			$where,
			__METHOD__
		);

		while ($friend = $results->fetchRow()) {
			$redis->sAdd($this->friendListRedisKey($friend['r_user_id']), $friend['r_user_id_relation']);
			$redis->sAdd($this->friendListRedisKey($friend['r_user_id_relation']), $friend['r_user_id']);
			$log("Added friendship between curse IDs {$friend['r_user_id']} and {$friend['r_user_id_relation']}", time());
		}

		$where = ['ur_type' => 1];
		if ($this->curse_id > 0) {
			$where[] = "(ur_user_id_from = ".$db->addQuotes($this->curse_id)." OR ur_user_id_to = ".$db->addQuotes($this->curse_id);
		}
		$results = $db->select(
			['user_relationship_request'],
			['*'],
			$where,
			__METHOD__
		);

		while ($friendReq = $results->fetchRow()) {
			$redis->hSet($this->requestsRedisKey($friendReq['ur_user_id_to']), $friendReq['ur_user_id_from'], '{}');
			$redis->sAdd($this->sentRequestsRedisKey($friendReq['ur_user_id_from']), $friendReq['ur_user_id_to']);
			$log("Added pending friendship between curse IDs {$friendReq['ur_user_id_to']} and {$friendReq['ur_user_id_from']}", time());
		}
	}

	/**
	 * This will write a given change to the database. Called by FriendSync job.
	 *
	 * @param	array	args sent to the FriendSync job
	 * @return	integer	exit code: 0 for success, 1 for failure
	 */
	public function saveToDB($args) {
		if (!defined('CURSEPROFILE_MASTER')) {
			return 1; // the appropriate tables don't exist here
		}
		$args['target'] = intval($args['target']);
		if ($args['target'] < 1) {
			return 1;
		}

		$db = CP::getDb(DB_MASTER);
		switch ($args['task']) {
			case 'add':
				$db->insert(
					'user_relationship_request',
					[
						'ur_user_id_from' => $this->curse_id,
						'ur_user_id_to'   => $args['target'],
						'ur_type'         => 1,
						'ur_date'         => date('Y-m-d H:i:s'),
					],
					__METHOD__
				);
				break;
			case 'confirm':
				$db->insert(
					'user_relationship',
					[
						'r_user_id'          => $this->curse_id,
						'r_user_id_relation' => $args['target'],
						'r_type'             => 1,
						'r_date'             => date('Y-m-d H:i:s'),
					],
					__METHOD__
				);
				$db->insert(
					'user_relationship',
					[
						'r_user_id'          => $args['target'],
						'r_user_id_relation' => $this->curse_id,
						'r_type'             => 1,
						'r_date'             => date('Y-m-d H:i:s'),
					],
					__METHOD__
				);
				//Intentional fall-through.
			case 'ignore':
				$db->delete(
					'user_relationship_request',
					[
						'ur_user_id_from'	=> $args['target'],
						'ur_user_id_to'		=> $this->curse_id
					],
					__METHOD__
				);
				break;
			case 'remove':
				$db->delete(
					'user_relationship',
					[
						'r_user_id'				=> $args['target'],
						'r_user_id_relation'	=> $this->curse_id
					],
					__METHOD__
				);
				$db->delete(
					'user_relationship',
					[
						'r_user_id'				=> $this->curse_id,
						'r_user_id_relation'	=> $args['target']
					],
					__METHOD__
				);
				$db->delete(
					'user_relationship_request',
					[
						'ur_user_id_from'	=> $this->curse_id,
						'ur_user_id_to'		=> $args['target']
					],
					__METHOD__
				);
				break;
			default:
				return 1;
		}
		return 0;
	}
}

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
		$toUser = intval($toUser);
		if ($this->globalId < 1 || $this->globalId == $toUser || $toUser < 1) {
			return -1;
		}

		$redis = \RedisCache::getClient('cache');
		if ($redis === false) {
			return -1;
		}

		try {
			//First check for existing friends.
			if ($redis->sIsMember($this->friendListRedisKey(), $toUser)) {
				return self::FRIENDS;
			}

			//Check for pending requests.
			if ($redis->hExists($this->requestsRedisKey(), $toUser)) {
				return self::REQUEST_RECEIVED;
			}
			if ($redis->hExists($this->requestsRedisKey($toUser), $this->globalId)) {
				return self::REQUEST_SENT;
			}
		} catch (\Exception $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return -1;
		}

		return self::STRANGERS; // assumption when not found in redis
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
		$redis = \RedisCache::getClient('cache');
		try {
			return $redis->sMembers($this->friendListRedisKey($user));
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
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
		if ($this->globalId < 1) {
			return 0;
		}

		if ($user == null) {
			$user = $this->globalId;
		}
		$redis = \RedisCache::getClient('cache');
		try {
			return intval($redis->sCard($this->friendListRedisKey($user)));
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}
		return 0;
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

		$redis = \RedisCache::getClient('cache');
		try {
			return $redis->hGetAll($this->requestsRedisKey());
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
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

		$redis = \RedisCache::getClient('cache');
		try {
			return $redis->sMembers($this->sentRequestsRedisKey());
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}
		return [];
	}

	/**
	 * Generates a redis key for the hash of pending requests received
	 *
	 * @param	integer	optional curse ID of a user (default $this->globalId)
	 * @return	string	redis key to be used
	 */
	private function requestsRedisKey($user = null) {
		if ($user == null) {
			$user = $this->globalId;
		}
		return 'friendrequests:'.$user;
	}

	/**
	 * Generates a redis key for the set of pending requests sent
	 *
	 * @param	integer	optional curse ID of a user (default $this->globalId)
	 * @return	string	redis key to be used
	 */
	private function sentRequestsRedisKey($user = null) {
		if ($user == null) {
			$user = $this->globalId;
		}
		return 'friendrequests:'.$user.':sent';
	}

	/**
	 * Generates a redis key for a set of friends
	 *
	 * @param	integer	optional curse ID of a user (default $this->globalId)
	 * @return	string	redis key to be used
	 */
	private function friendListRedisKey($user = null) {
		if ($user == null) {
			$user = $this->globalId;
		}
		return 'friendlist:'.$user;
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

		$toGlobalId = intval($toGlobalId);
		$lookup = \CentralIdLookup::factory();
		$toLocalUser = $lookup->localUserFromCentralId($toGlobalId);
		if ($this->globalId < 1 || $this->globalId == $toGlobalId || $toGlobalId < 1 || !$toLocalUser->getId()) {
			return false;
		}

		//Queue sync before error check in case redis is not in sync.
		FriendSync::queue([
			'task' => 'add',
			'actor' => $this->globalId,
			'target' => $toGlobalId,
		]);
		if ($this->getRelationship($toGlobalId) != self::STRANGERS) {
			return false;
		}

		$redis = \RedisCache::getClient('cache');
		try {
			$redis->hSet($this->requestsRedisKey($toGlobalId), $this->globalId, '{}');
			$redis->sAdd($this->sentRequestsRedisKey(), $toGlobalId);
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}

		\EchoEvent::create([
			'type' => 'friendship-request',
			'agent' => $wgUser,
			'title' => $wgUser->getUserPage(),
			'extra' => [
				'target_user_id' => $toLocalUser->getId()
			]
		]);

		wfRunHooks('CurseProfileAddFriend', [$this->globalId, $toGlobalId]);

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
		wfRunHooks('CurseProfileAcceptFriend', [$this->globalId, $toGlobalId]);
		return $this->respondToRequest($toGlobalId, 'accept');
	}

	/**
	 * Ignores and dismisses a pending request
	 *
	 * @access	public
	 * @param	integer	curse ID of a user
	 * @return	bool	true on success, false on failure
	 */
	public function ignoreRequest($toGlobalId) {
		return $this->respondToRequest($toGlobalId, 'ignore');
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
		if ($this->globalId < 1 || $this->globalId == $toUser || $toUser < 1) {
			return -1;
		}

		FriendSync::queue([
			'task' => ($response == 'accept' ? 'confirm' : 'ignore'),
			'actor' => $this->globalId,
			'target' => $toUser
		]);
		if ($this->getRelationship($toUser) != self::REQUEST_RECEIVED) {
			return false;
		}

		$redis = \RedisCache::getClient('cache');
		try {
			// delete pending request
			$redis->hDel($this->requestsRedisKey(), $toUser);
			$redis->sRem($this->sentRequestsRedisKey($toUser), $this->globalId);

			if ($response == 'accept') {
				// add reciprocal friendship
				$redis->sAdd($this->friendListRedisKey(), $toUser);
				$redis->sAdd($this->friendListRedisKey($toUser), $this->globalId);
			}
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
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
		if ($this->globalId < 1 || $this->globalId == $toUser || $toUser < 1) {
			return false;
		}

		FriendSync::queue([
			'task' => 'remove',
			'actor' => $this->globalId,
			'target' => $toUser
		]);

		$redis = \RedisCache::getClient('cache');
		try {
			// remove pending incoming requests
			$redis->hDel($this->requestsRedisKey($toUser), $this->globalId);
			$redis->hDel($this->requestsRedisKey(), $toUser);

			// remove sent request references
			$redis->sRem($this->sentRequestsRedisKey($toUser), $this->globalId);
			$redis->sRem($this->sentRequestsRedisKey(), $toUser);

			// remove existing friendship
			$redis->sRem($this->friendListRedisKey($toUser), $this->globalId);
			$redis->sRem($this->friendListRedisKey(), $toUser);
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}

		wfRunHooks('CurseProfileRemoveFriend', [$this->globalId, $toUser]);

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
		if (!defined('MASTER_WIKI') || MASTER_WIKI === false) {
			return;
		}
		if (is_null($logger)) {
			$log = function($str, $time=null) {};
		} else {
			$log = function($str, $time=null) use ($logger) {
				$logger->outputLine($str, $time);
			};
		}
		$redis = \RedisCache::getClient('cache');
		$db = CP::getDb(DB_MASTER);

		$where = ['r_type' => 1];
		if ($this->globalId > 0) {
			$where[] = "(r_user_id = ".$db->addQuotes($this->globalId)." OR r_user_id_relation = ".$db->addQuotes($this->globalId);
		}
		$results = $db->select(
			['user_relationship'],
			['*'],
			$where,
			__METHOD__
		);

		try {
			while ($friend = $results->fetchRow()) {
				$redis->sAdd($this->friendListRedisKey($friend['r_user_id']), $friend['r_user_id_relation']);
				$redis->sAdd($this->friendListRedisKey($friend['r_user_id_relation']), $friend['r_user_id']);
				$log("Added friendship between curse IDs {$friend['r_user_id']} and {$friend['r_user_id_relation']}", time());
			}
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		$where = ['ur_type' => 1];
		if ($this->globalId > 0) {
			$where[] = "(ur_user_id_from = ".$db->addQuotes($this->globalId)." OR ur_user_id_to = ".$db->addQuotes($this->globalId);
		}
		$results = $db->select(
			['user_relationship_request'],
			['*'],
			$where,
			__METHOD__
		);

		try {
			while ($friendReq = $results->fetchRow()) {
				$redis->hSet($this->requestsRedisKey($friendReq['ur_user_id_to']), $friendReq['ur_user_id_from'], '{}');
				$redis->sAdd($this->sentRequestsRedisKey($friendReq['ur_user_id_from']), $friendReq['ur_user_id_to']);
				$log("Added pending friendship between curse IDs {$friendReq['ur_user_id_to']} and {$friendReq['ur_user_id_from']}", time());
			}
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}
	}

	/**
	 * This will write a given change to the database. Called by FriendSync job.
	 *
	 * @param	array	args sent to the FriendSync job
	 * @return	integer	exit code: 0 for success, 1 for failure
	 */
	public function saveToDB($args) {
		if (!defined('MASTER_WIKI') || MASTER_WIKI === false) {
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
						'ur_user_id_from' => $this->globalId,
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
						'r_user_id'          => $this->globalId,
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
						'r_user_id_relation' => $this->globalId,
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
						'ur_user_id_to'		=> $this->globalId
					],
					__METHOD__
				);
				break;
			case 'remove':
				$db->delete(
					'user_relationship',
					[
						'r_user_id'				=> $args['target'],
						'r_user_id_relation'	=> $this->globalId
					],
					__METHOD__
				);
				$db->delete(
					'user_relationship',
					[
						'r_user_id'				=> $this->globalId,
						'r_user_id_relation'	=> $args['target']
					],
					__METHOD__
				);
				$db->delete(
					'user_relationship_request',
					[
						'ur_user_id_from'	=> $this->globalId,
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

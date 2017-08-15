<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

/**
 * Class that manages user-reported profile comments
 */
class CommentReport {
	// actions to take on a report
	const ACTION_NONE = 0;
	const ACTION_DISMISS = 1;
	const ACTION_DELETE = 2;

	// keys for redis
	const REDIS_KEY_REPORTS = 'cp:reportedcomments';
	const REDIS_KEY_VOLUME_INDEX = 'cp:reportedcomments:byvolume';
	const REDIS_KEY_DATE_INDEX = 'cp:reportedcomments:bydate';
	const REDIS_KEY_USER_INDEX = 'cp:reportedcomments:byuser:';
	const REDIS_KEY_WIKI_INDEX = 'cp:reportedcomments:bywiki:';
	const REDIS_KEY_ACTED_INDEX = 'cp:reportedcomments:processed';

	/**
	 * The structured data that is serialized into redis
	 * @var		array
	 */
	public $data;

	/**
	 * ID from the ra_id column of user_board_report_archives
	 * @var		int
	 */
	private $id = 0;

	/**
	 * Constructor used by static methods to create instances of this class
	 *
	 * @param	array	$data a mostly filled out data set (see newFromRow)
	 * @return	void
	 */
	private function __construct($data) {
		$this->data = $data;
	}

	/**
	 * Gets the total count of how many comments are in a given queue
	 *
	 * @param	string	$sortStyle which queue to count
	 * @param	string	$qualifier [optional] site md5key or curse id when $sortStyle is 'byWiki' or 'byUser'
	 * @return	int
	 */
	public static function getCount($sortStyle, $qualifier = null) {
		$redis = \RedisCache::getClient('cache');

		try {
			// TODO: alternately query the DB directly if this is not running on the master wiki
			switch ($sortStyle) {
				case 'byWiki':
					return $redis->zCard(self::REDIS_KEY_WIKI_INDEX.$qualifier);

				case 'byUser':
					return $redis->zCard(self::REDIS_KEY_USER_INDEX.$qualifier);

				case 'byDate':
				case 'byVolume':
				default: // date and volume keys should always be the same
					return $redis->zCard(self::REDIS_KEY_VOLUME_INDEX);
			}
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}
		return 0;
	}

	/**
	 * Main retrieval function to get data out of redis or the local db
	 *
	 * @param	string	$sortStyle [optional] default byVolume
	 * @param	integer	$limit [optional] default 10
	 * @param	integer	$offset [optional] default 0
	 * @return	array
	 */
	public static function getReports($sortStyle = 'byVolume', $limit = 10, $offset = 0) {
		if (defined('MASTER_WIKI') && MASTER_WIKI === true) {
			return self::getReportsRedis($sortStyle, $limit, $offset);
		} else {
			return self::getReportsDb($sortStyle, $limit, $offset);
		}
	}

	/**
	 * Retrieve a single report by its unique id
	 *
	 * @param	string	report key as retrieved from reportKey()
	 * @param	bool	[optional] when set to true, will return null if report does not exist in the current wiki
	 * @return	obj		CommentReport instance or null if report does not exist
	 */
	public static function newFromKey($key, $onlyLocal = false) {
		global $dsSiteKey;
		if (strpos($key, $dsSiteKey) != 0) {
			// report is remote
			if ($onlyLocal) {
				return null;
			}
			try {
				$redis = \RedisCache::getClient('cache');
				$report = $redis->hGet(self::REDIS_KEY_REPORTS, $key);
			} catch (\Throwable $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
				return null;
			}
			if ($report !== false) {
				return new self(unserialize($report));
			} else {
				return null;
			}
		} else {
			// report is local
			list($md5key, $comment_id, $timestamp) = explode(':', $key);
			$db = CP::getDb(DB_SLAVE);
			$row = $db->selectRow(
				'user_board_report_archives',
				['*'],
				['ra_comment_id='.intval($comment_id), 'ra_last_edited="'.date('Y-m-d H:i:s', $timestamp).'"'],
				__METHOD__
			);
			if ($row) {
				return self::newFromRow((array)$row);
			} else {
				return null;
			}
		}
	}

	/**
	 * Queries redis data store for reports
	 *
	 * @param	string	sort style
	 * @param	integer	max number of reports to return
	 * @param	integer	offset
	 * @return	array	0 or more CommentReport instances
	 */
	private static function getReportsRedis($sortStyle, $limit, $offest) {
		$redis = \RedisCache::getClient('cache');

		$reports = [];

		try {
			switch ($sortStyle) {
				case 'byActionDate':
					$keys = $redis->zRevRange(self::REDIS_KEY_ACTED_INDEX, $offset, $limit+$offset);
					break;

				case 'byVolume':
				default:
					$keys = $redis->zRevRange(self::REDIS_KEY_VOLUME_INDEX, $offest, $limit+$offset);
			}

			if (count($keys)) {
				$reports = $redis->hMGet(self::REDIS_KEY_REPORTS, $keys);
				$reports = array_map(function($rep) { return new self(unserialize($rep)); }, $reports);
			}
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		return $reports;
	}

	/**
	 * Queries the local db for reports
	 *
	 * @param	string	sort style
	 * @param	integer	max number of reports to return
	 * @param	integer	offset
	 * @return	array	0 or more CommentReport instances
	 */
	private static function getReportsDb($sortStyle, $limit, $offset) {
		$db = CP::getDb(DB_SLAVE);
		$reports = [];
		switch ($sortStyle) {
			case 'byActionDate':
				$res = $db->select(
					['user_board_report_archives'],
					['*'],
					['ra_action_taken != 0'],
					__METHOD__,
					[
						'ORDER BY' => 'ra_action_taken_at DESC',
						'LIMIT' => $limit,
						'OFFSET' => $offset,
					]
				);
				break;

			case 'byVolume':
			default:
				// TODO alter scheme to have an incrementing count in the archive table to avoid using a slow count(*) query
				$subTable = '(select ubr_report_archive_id, count(*) as report_count from user_board_reports group by ubr_report_archive_id) AS ubr';
				$res = $db->select(
					[
						'user_board_report_archives AS ra',
						$subTable,
					],
					['ra.*', 'report_count'],
					['ra_action_taken = 0'],
					__METHOD__,
					[
						'ORDER BY' => 'report_count DESC',
						'LIMIT' => $limit,
						'OFFSET' => $offset,
					],
					[
						$subTable => [
							'LEFT JOIN',['ra_id=ubr_report_archive_id']
						]
					]
				);
				break;
		}
		if ($res) {
			foreach ($res as $row) {
					$reports[] = self::newFromRow((array)$row);
			}
		}
		return $reports;
	}

	/**
	 * Primary entry point for a user clicking the report button.
	 * Assumes $wgUser is the acting reporter
	 *
	 * @param	integer	id of a local comment
	 * @return	mixed	CommentReport instance that is already saved or null on failure
	 */
	public static function newUserReport($comment_id) {
		$db = CP::getDb(DB_SLAVE);
		$comment_id = intval($comment_id);
		if ($comment_id < 1) {
			return null;
		}

		// get last touched timestamp
		$res = $db->select(
			['user_board'],
			['ub_id', 'ub_user_id_from', 'ub_date', 'ub_edited', 'ub_message'],
			["ub_id = $comment_id", "ub_type = ".CommentBoard::PUBLIC_MESSAGE],
			__METHOD__
		);
		$comment = $res->fetchRow();
		$res->free();

		if (!$comment) {
			// comment did not exist, is already deleted, or a private message (legacy feature of leaguepedia's old profile system)
			return null;
		}

		// check for existing reports// Look up the target comment
		$comment['last_touched'] = $comment['ub_edited'] ? $comment['ub_edited'] : $comment['ub_date'];
		$res = $db->select(
			['user_board_report_archives'],
			['ra_id', 'ra_comment_id', 'ra_last_edited', 'ra_action_taken'],
			["ra_comment_id = $comment_id", "ra_last_edited = '{$comment['last_touched']}'"],
			__METHOD__
		);
		$reportRow = $res->fetchRow();
		$res->free();

		if (!$reportRow) {
			// create new report item if never reported before
			$report = self::createWithArchive($comment);
		} elseif ($reportRow['ra_action_taken']) {
			// comment has already been moderated
			return self::newFromRow($reportRow);
		} else {
			// add report to existing archive
			//$report = self::addReportTo($reportRow['ra_id']); //?_?  Never implemented?
		}

		return $report;
	}

	/**
	 * Archive the contents of a comment into a new report
	 *
	 * @param	array	comment row from the user_board DB table
	 * @return	obj		CommentReport instance
	 */
	private static function createWithArchive($comment) {
		global $wgUser, $dsSiteKey;

		$lookup = \CentralIdLookup::factory();
		$userFrom = \User::newFromId($comment['ub_user_id_from']);
		if (!$userFrom) {
			return false;
		}
		$authorGlobalId = $lookup->centralIdFromLocalUser($userFrom);

		// insert data into redis and update indexes
		$data = [
			'comment' => [
				'text' => $comment['ub_message'],
				'cid' => $comment['ub_id'],
				'origin_wiki' => $dsSiteKey,
				'last_touched' => strtotime($comment['last_touched']),
				'author' => $authorGlobalId,
			],
			'reports' => [],
			'action_taken' => 0,
			'action_taken_by' => null,
			'action_taken_at' => null,
			'first_reported' => time(),
		];
		$report = new self($data);
		$report->initialLocalInsert();
		if ($report->id == 0) {
			return false;
		}
		$report->initialRedisInsert();

		$report->addReportFrom($wgUser);

		return $report;
	}

	/**
	 * Creates a new comment report object from a DB row
	 *
	 * @param	array	row from the the user_board_report_archives table
	 * @return	obj		CommentReport instance
	 */
	private static function newFromRow($report) {
		global $dsSiteKey;
		$data = [
			'comment' => [
				'text' => $report['ra_comment_text'],
				'cid' => $report['ra_comment_id'],
				'origin_wiki' => $dsSiteKey,
				'last_touched' => strtotime($report['ra_last_edited']),
				'author' => $report['ra_global_id_from'],
			],
			'reports' => self::getReportsForId($report['ra_id']),
			'action_taken' => $report['ra_action_taken'],
			'action_taken_by' => $report['ra_action_taken_by'],
			'action_taken_at' => strtotime($report['ra_action_taken_at']),
			'first_reported' => strtotime($report['ra_first_reported']),
		];
		$cr = new self($data);
		$cr->id = $report['ra_id'];
		return $cr;
	}

	/**
	 * Loads individual user reports for a given comment report
	 *
	 * @param   int     the ra_comment_id from the user_board_report_archives table
	 * @return  array   with sub arrays for each report having keys reporter => global_id, timestamp
	 */
	private static function getReportsForId($id) {
		$db = CP::getDb(DB_SLAVE);
		$res = $db->select(
			['user_board_reports'],
			['ubr_reporter_global_id as reporter', 'ubr_reported as timestamp'],
			['ubr_report_archive_id = '.intval($id)],
			__METHOD__,
			[ 'ORDER BY' => 'ubr_reported ASC' ]
		);
		$reports = [];
		foreach ($res as $row) {
			$report = (array)$row;
			$report['timestamp'] = strtotime($report['timestamp']);
			$reports[] = $report;
		}
		return $reports;
	}

	/**
	 * @return	bool	true if report is stored on this wiki
	 */
	public function isLocal() {
		global $dsSiteKey;
		return $dsSiteKey == $this->data['comment']['origin_wiki'];
	}

	/**
	 * @return	bool	true if report is stored on this wiki
	 */
	public static function keyIsLocal($reportKey) {
		global $dsSiteKey;
		list($siteKey) = explode(':', $reportKey);
		return $dsSiteKey == $siteKey;
	}

	/**
	 * Insert a new report into the local database
	 *
	 * @access	private
	 * @return	void
	 */
	private function initialLocalInsert() {
		// insert into local db tables
		$db = CP::getDb(DB_MASTER);
		$db->insert('user_board_report_archives', [
			'ra_comment_id' => $this->data['comment']['cid'],
			'ra_last_edited' => date('Y-m-d H:i:s', $this->data['comment']['last_touched']),
			'ra_global_id_from' => $this->data['comment']['author'],
			'ra_comment_text' => $this->data['comment']['text'],
			'ra_first_reported' => date('Y-m-d H:i:s', $this->data['first_reported']),
			'ra_action_taken' => $this->data['action_taken'],
			'ra_action_taken_by' => $this->data['action_taken_by'],
			'ra_action_taken_at' => date('Y-m-d H:i:s', $this->data['action_taken_at']),
		], __METHOD__);
		$this->id = $db->insertId();
	}

	/**
	 * Insert a new report into redis with indexes
	 *
	 * @access	private
	 * @return	boolean	Success
	 */
	private function initialRedisInsert() {
		$redis = \RedisCache::getClient('cache');
		$commentKey = $this->reportKey();
		$date = $this->data['first_reported'];

		try {
			// serialize data into redis
			$redis->hSet(self::REDIS_KEY_REPORTS, $commentKey, serialize($this->data));

			// add appropriate indexes
			$redis->zAdd(self::REDIS_KEY_DATE_INDEX, $date, $commentKey);
			$redis->zAdd(self::REDIS_KEY_WIKI_INDEX.$this->data['comment']['origin_wiki'], $date, $commentKey);
			$redis->zAdd(self::REDIS_KEY_USER_INDEX.$this->data['comment']['author'], $date, $commentKey);
			$redis->zAdd(self::REDIS_KEY_VOLUME_INDEX, 0, $commentKey);
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}
		return true;
	}

	/**
	 * Get the unique key identifying this reported comment in redis
	 *
	 * @access	public
	 * @return	string
	 */
	public function reportKey() {
		return sprintf('%s:%s:%s',
			$this->data['comment']['origin_wiki'],
			$this->data['comment']['cid'],
			$this->data['comment']['last_touched']
		);
	}

	/**
	 * Add a new report to comment that has already been archived
	 *
	 * @access	private
	 * @param	object	The User reporting this comment
	 * @return	void
	 */
	private function addReportFrom($user) {
		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);

		if (!isset($this->id) || !$globalId) {
			return false; // can't add to a comment that hasn't been archived yet
		}

		$newReport = [
			'reporter' => $globalId,
			'timestamp' => time(),
		];

		// add new report row to local DB
		$db = CP::getDb(DB_MASTER);
		$db->insert('user_board_reports', [
			'ubr_report_archive_id' => $this->id,
			'ubr_reporter_id' => $user->getId(),
			'ubr_reporter_global_id' => $globalId,
			'ubr_reported' => date('Y-m-d H:i:s', $newReport['timestamp']),
		], __METHOD__);

		$redis = \RedisCache::getClient('cache');

		try {
			// increment volume index in redis
			$redis->zIncrBy(self::REDIS_KEY_VOLUME_INDEX, 1, $this->reportKey());

			// update serialized redis data
			$this->data['reports'][] = $newReport;
			$redis->hSet(self::REDIS_KEY_REPORTS, $this->reportKey(), serialize($this->data));
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}
	}

	/**
	 * Dismiss or delete a reported comment/
	 *
	 * @param	string	Action to take on the reported comment. either 'delete' or 'dismiss'
	 * @param	mixed	[Optional] User object of the acting user, defaults to $wgUser.
	 * @return	boolean	true if successful
	 */
	public function resolve($action, $byUser = null) {
		global $wgUser;

		if (!$this->isLocal()) {
			return false;
		}
		if ($this->data['action_taken']) {
			return false;
		}

		if (is_null($byUser)) {
			$byUser = $wgUser;
		}
		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($byUser, \CentralIdLookup::AUDIENCE_RAW);

		//Need a Curse ID to continue;
		if (!$globalId) {
			return false;
		}

		// update internal data
		$this->data['action_taken']		= ($action === 'delete' ? self::ACTION_DELETE : self::ACTION_DISMISS);
		$this->data['action_taken_by']	= $globalId;
		$this->data['action_taken_at']	= time();

		// update data stores
		return ( $action == 'dismiss' || CommentBoard::removeComment($this->data['comment']['cid'], $byUser) )
			&& $this->resolveInDb()
			&& $this->resolveInRedis();
	}

	/**
	 * Marks a report as archived in the local database
	 *
	 * @return	bool	success
	 */
	private function resolveInDb() {
		// write 1 or 2 to ra_action_taken column
		// write curse ID of acting user to ra_action_taken_by
		$db = CP::getDb(DB_MASTER);
		$result = $db->update(
			'user_board_report_archives',
			[
				'ra_action_taken' => $this->data['action_taken'],
				'ra_action_taken_by' => $this->data['action_taken_by'],
				'ra_action_taken_at' => date('Y-m-d H:i:s', $this->data['action_taken_at']),
			],[
				'ra_comment_id' => intval($this->data['comment']['cid']),
				'ra_last_edited' => date('Y-m-d H:i:s', $this->data['comment']['last_touched'])
			],
			__METHOD__
		);
		return $result;
	}

	/**
	 * Marks a report as archived in redis
	 *
	 * @return	bool	true
	 */
	private function resolveInRedis() {
		$redis = \RedisCache::getClient('cache');

		try {
			// add key to index for actioned items
			$redis->zAdd(self::REDIS_KEY_ACTED_INDEX, $this->data['action_taken_at'], $this->reportKey());

			// update serialized data
			$redis->hSet(self::REDIS_KEY_REPORTS, $this->reportKey(), serialize($this->data));

			// remove key from non-actioned item indexes
			$redis->zRem(self::REDIS_KEY_VOLUME_INDEX, $this->reportKey());
			$redis->zRem(self::REDIS_KEY_DATE_INDEX, $this->reportKey());
			$redis->zRem(self::REDIS_KEY_USER_INDEX.$this->data['comment']['author'], $this->reportKey());
			$redis->zRem(self::REDIS_KEY_WIKI_INDEX.$this->data['comment']['origin_wiki'], $this->reportKey());
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}

		return true;
	}
}

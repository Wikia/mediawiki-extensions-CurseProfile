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
		$mouse = CP::loadMouse();
		// TODO: alternately query the DB directly if this is not running on the master wiki
		switch ($sortStyle) {
			case 'byWiki':
				return $mouse->redis->zcard(self::REDIS_KEY_WIKI_INDEX.$qualifier);

			case 'byUser':
				return $mouse->redis->zcard(self::REDIS_KEY_USER_INDEX.$qualifier);

			case 'byDate':
			case 'byVolume':
			default: // date and volume keys should always be the same
				return $mouse->redis->zcard(self::REDIS_KEY_VOLUME_INDEX);
		}
	}

	/**
	 * Main retrieval function to get data out of redis or the local db
	 *
	 * @param	string	$sortStyle [optional] default byVolume
	 * @param	int		$limit [optional] default 10
	 * @param	int		$offset [optional] default 0
	 * @return	array
	 */
	public static function getReports($sortStyle = 'byVolume', $limit = 10, $offset = 0) {
		if (defined('CURSEPROFILE_MASTER')) {
			return self::getReportsRedis($sortStyle, $limit, $offset);
		} else {
			return self::getReportsDb($sortStyle, $limit, $offset);
		}
	}

	private static function getReportsRedis($sortStyle, $limit, $offest) {
		$mouse = CP::loadMouse();
		switch ($sortStyle) {
			case 'byVolume':
			default:
				$keys = $mouse->redis->zrevrange(self::REDIS_KEY_VOLUME_INDEX, $offest, $limit+$offset);
				if (count($keys)) {
					// prepend key value to prep mass retrieval from redis
					$keys = array_merge([self::REDIS_KEY_REPORTS], $keys);
					$reports = call_user_func_array([$mouse->redis, 'hmget'], $keys);
					$reports = array_map(function($rep) { return new self(unserialize($rep)); }, $reports);
				} else {
					$reports = [];
				}
		}
		return $reports;
	}

	private static function getReportsDb($sortStyle, $limit, $offset) {
		$db = wfGetDB(DB_SLAVE);
		$reports = [];
		switch ($sortStyle) {
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
	 * @param	int		id of a local comment
	 * @return	mixed	CommentReport instance that is already saved or null on failure
	 */
	public static function newUserReport($comment_id) {
		$db = wfGetDB(DB_SLAVE);
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
			$report = self::addReportTo($reportRow['ra_id']);
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
		$authorCurseId = CP::curseIDfromUserID($comment['ub_user_id_from']);

		// insert data into redis and update indexes
		$data = [
			'comment' => [
				'text' => $comment['ub_message'],
				'cid' => $comment['ub_id'],
				'origin_wiki' => $dsSiteKey,
				'last_touched' => $comment['last_touched'],
				'author' => $authorCurseId,
			],
			'reports' => [],
			'action_taken' => 0,
			'action_taken_by' => null,
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
				'last_touched' => $report['ra_last_edited'],
				'author' => $report['ra_curse_id_from'],
			],
			'reports' => [], // TODO could be loaded now or on demand with a to-be-written getReports() method
			'action_taken' => $report['ra_action_taken'],
			'action_taken_by' => $report['ra_action_taken_by'],
			'first_reported' => $report['ra_first_reported'],
		];
		$cr = new self($data);
		return $cr;
	}

	/**
	 * Insert a new report into the local database
	 *
	 * @access	private
	 * @return	void
	 */
	private function initialLocalInsert() {
		// insert into local db tables
		$db = wfGetDB( DB_MASTER );
		$db->insert('user_board_report_archives', [
			'ra_comment_id' => $this->data['comment']['cid'],
			'ra_last_edited' => $this->data['comment']['last_touched'],
			// 'ra_user_id_from' => $this->data['comment']['ub_user_id_from'], // potentially deprecating the use of local user IDs here
			'ra_curse_id_from' => $this->data['comment']['author'],
			'ra_comment_text' => $this->data['comment']['text'],
			'ra_first_reported' => date('Y-m-d H:i:s', $this->data['first_reported']),
			'ra_action_taken' => $this->data['action_taken'],
			'ra_action_taken_by' => $this->data['action_taken_by'],
		], __METHOD__);
		$this->id = $db->insertId();
	}

	/**
	 * Insert a new report into redis with indexes
	 *
	 * @access	private
	 * @return	void
	 */
	private function initialRedisInsert() {
		$mouse = CP::loadMouse();
		$commentKey = $this->redisReportKey();
		$date = $this->data['first_reported'];
		$mouse->redis->hset(self::REDIS_KEY_REPORTS, $commentKey, serialize($this->data));
		$mouse->redis->zadd(self::REDIS_KEY_DATE_INDEX, $date, $commentKey);
		$mouse->redis->zadd(self::REDIS_KEY_WIKI_INDEX.$this->data['comment']['origin_wiki'], $date, $commentKey);
		$mouse->redis->zadd(self::REDIS_KEY_USER_INDEX.$authorCurseId, $date, $commentKey);
		$mouse->redis->zadd(self::REDIS_KEY_VOLUME_INDEX, 0, $commentKey);
	}

	/**
	 * Get the unique key identifying this reported comment in redis
	 *
	 * @access	private
	 * @return	string
	 */
	private function redisReportKey() {
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
	 * @param	User	the user reporting this comment
	 * @return	void
	 */
	private function addReportFrom($user) {
		if (!isset($this->id)) {
			return false; // can't add to a comment that hasn't been archived yet
		}

		// add new report row to local DB
		$db = wfGetDB( DB_MASTER );
		$db->insert('user_board_reports', [
			'ubr_report_archive_id' => $this->id,
			'ubr_reporter_id' => $user->getId(),
			'ubr_reporter_curse_id' => $user->curse_id,
			'ubr_reported' => date('Y-m-d H:i:s'),
		], __METHOD__);

		// increment volume index in redis
		$mouse = CP::loadMouse();
		$mouse->redis->zincrby(self::REDIS_KEY_VOLUME_INDEX, 1, $this->redisReportKey());
	}

	/**
	 * Dismiss or delete a reported comment
	 */
	public static function archiveReport($action) {

	}

	private static function archiveLocalReport() {
		// write 1 or 2 to ra_action_taken column
		// write curse ID of acting user to ra_action_taken_by
	}

	private static function archiveRedisReport() {
		// add key to index for actioned items
	}
}

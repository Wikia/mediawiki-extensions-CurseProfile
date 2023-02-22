<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Classes;

use MediaWiki\MediaWikiServices;
use Reverb\Notification\NotificationFactory;
use SpecialPage;
use Title;
use User;

/**
 * Class that manages user-reported profile comments
 */
class CommentReport {
	// actions to take on a report
	public const ACTION_NONE = 0;
	public const ACTION_DISMISS = 1;
	public const ACTION_DELETE = 2;

	/**
	 * The structured data.
	 *
	 * @var	array
	 */
	public $data;

	/**
	 * ID from the ra_id column of user_board_report_archives.
	 *
	 * @var	int
	 */
	private $id = 0;

	/**
	 * Constructor used by static methods to create instances of this class.
	 *
	 * @param array $data a mostly filled out data set (see newFromRow)
	 * @return void
	 */
	private function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Gets the total count of how many comments are in a given queue
	 *
	 * @param string $sortStyle which queue to count
	 * @param string|null $qualifier [optional] site md5key or curse id when $sortStyle is 'byWiki' or 'byUser'
	 * @return int
	 */
	public static function getCount( $sortStyle, $qualifier = null ) {
		return 0;
	}

	/**
	 * Main retrieval function to get data.
	 *
	 * @param string $sortStyle [optional] default byVolume
	 * @param int $limit [optional] default 10
	 * @param int $offset [optional] default 0
	 * @return array
	 */
	public static function getReports( $sortStyle = 'byVolume', $limit = 10, $offset = 0 ) {
		return self::getReportsDb( $sortStyle, $limit, $offset );
	}

	/**
	 * Retrieve a single report by its unique id
	 *
	 * @param string $key report key as retrieved from reportKey()
	 * @return mixed CommentReport instance or null if report does not exist
	 */
	public static function newFromKey( $key ) {
		// report is local
		[ $md5key, $commentId, $timestamp ] = explode( ':', $key );
		$db = CP::getDb( DB_REPLICA );
		$row = $db->selectRow(
			'user_board_report_archives',
			[ '*' ],
			[
				'ra_comment_id' => (int)$commentId,
				'ra_last_edited' => date( 'Y-m-d H:i:s', $timestamp )
			],
			__METHOD__
		);
		if ( $row ) {
			return self::newFromRow( (array)$row );
		}

		return null;
	}

	/**
	 * Queries the local db for reports
	 *
	 * @param string $sortStyle sort style
	 * @param int $limit max number of reports to return
	 * @param int $offset offset
	 * @return array 0 or more CommentReport instances
	 */
	private static function getReportsDb( $sortStyle, $limit, $offset ) {
		$db = CP::getDb( DB_REPLICA );
		$reports = [];
		switch ( $sortStyle ) {
			case 'byActionDate':
				$res = $db->select(
					[ 'user_board_report_archives' ],
					[ '*' ],
					[ 'ra_action_taken != 0' ],
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
				// @TODO alter scheme to have an incrementing count
				// in the archive table to avoid using a slow count(*) query
				$subTable =
					'(select ubr_report_archive_id, count(*) as report_count from user_board_reports group by ubr_report_archive_id) AS ubr';
				$res = $db->select(
					[
						'user_board_report_archives AS ra',
						$subTable,
					],
					[ 'ra.*', 'report_count' ],
					[ 'ra_action_taken' => 0 ],
					__METHOD__,
					[
						'ORDER BY' => 'report_count DESC',
						'LIMIT' => $limit,
						'OFFSET' => $offset,
					],
					[
						$subTable => [
							'LEFT JOIN',
							[ 'ra_id = ubr_report_archive_id' ]
						]
					]
				);
				break;
		}
		if ( $res ) {
			foreach ( $res as $row ) {
					$reports[] = self::newFromRow( (array)$row );
			}
		}
		return $reports;
	}

	/**
	 * Primary entry point for a user clicking the report button.
	 * Assumes $wgUser is the acting reporter
	 *
	 * @param Comment $comment The comment being reported.
	 * @param User $actor User creating this report.
	 *
	 * @return mixed CommentReport instance that is already saved or false on failure.
	 */
	public static function newUserReport( Comment $comment, User $actor ) {
		$db = CP::getDb( DB_REPLICA );

		if ( $comment->getId() < 1 ) {
			return false;
		}

		// check for existing reports// Look up the target comment
		$lastTouched = $comment->getEditTimestamp() ? $comment->getEditTimestamp() : $comment->getPostTimestamp();
		$res = $db->select(
			[ 'user_board_report_archives' ],
			[ '*' ],
			[
				"ra_comment_id" => $comment->getId(),
				"ra_last_edited" => date( 'Y-m-d H:i:s', $lastTouched )
			],
			__METHOD__
		);
		$reportRow = $res->fetchRow();
		$res->free();

		if ( !$reportRow ) {
			// create new report item if never reported before
			$report = self::createWithArchive( $comment, $actor );
		} elseif ( $reportRow['ra_action_taken'] ) {
			// comment has already been moderated
			return self::newFromRow( $reportRow );
		} else {
			// add report to existing archive
			// $report = self::addReportTo($reportRow['ra_id']); //?_?  Never implemented?
			return self::newFromRow( $reportRow );
		}

		return $report;
	}

	/**
	 * Archive the contents of a comment into a new report
	 *
	 * @param Comment $comment The comment being reported.
	 * @param User $actor User creating this report.
	 *
	 * @return mixed CommentReport instance
	 */
	private static function createWithArchive( Comment $comment, User $actor ) {
		$userFrom = $comment->getActorUser();
		if ( !$userFrom ) {
			return false;
		}

		// insert data and update indexes
		$data = [
			'comment' => [
				'text' => $comment->getMessage(),
				'cid' => $comment->getId(),
				'last_touched' =>
					$comment->getEditTimestamp() ? $comment->getEditTimestamp() : $comment->getPostTimestamp(),
				'author' => $userFrom->getId(),
			],
			'reports' => [],
			'action_taken' => 0,
			'action_taken_by' => null,
			'action_taken_at' => null,
			'first_reported' => time(),
		];
		$report = new self( $data );
		$report->initialLocalInsert();
		if ( $report->id == 0 ) {
			return false;
		}

		$report->addReportFrom( $actor );

		return $report;
	}

	/**
	 * Creates a new comment report object from a DB row.
	 *
	 * @param array $report Row from the the user_board_report_archives table.
	 *
	 * @return CommentReport
	 */
	private static function newFromRow( $report ) {
		$data = [
			'comment' => [
				'text' => $report['ra_comment_text'],
				'cid' => $report['ra_comment_id'],
				'last_touched' => strtotime( $report['ra_last_edited'] ),
				'author' => $report['ra_user_id_from'],
			],
			'reports' => self::getReportsForId( $report['ra_id'] ),
			'action_taken' => $report['ra_action_taken'],
			'action_taken_by' => $report['ra_action_taken_by_user_id'],
			'action_taken_at' => strtotime( $report['ra_action_taken_at'] ),
			'first_reported' => strtotime( $report['ra_first_reported'] ),
		];
		$cr = new self( $data );
		$cr->id = $report['ra_id'];
		return $cr;
	}

	/**
	 * Loads individual user reports for a given comment report.
	 *
	 * @param int $id The ra_comment_id from the user_board_report_archives table
	 *
	 * @return array With sub arrays for each report having keys reporter => user_id, timestamp
	 */
	private static function getReportsForId( $id ) {
		$db = CP::getDb( DB_REPLICA );
		$res = $db->select(
			[ 'user_board_reports' ],
			[ 'ubr_reporter_user_id as reporter', 'ubr_reported as timestamp' ],
			[ 'ubr_report_archive_id = ' . intval( $id ) ],
			__METHOD__,
			[ 'ORDER BY' => 'ubr_reported ASC' ]
		);
		$reports = [];
		foreach ( $res as $row ) {
			$report = (array)$row;
			$report['timestamp'] = strtotime( $report['timestamp'] );
			$reports[] = $report;
		}
		return $reports;
	}

	/**
	 * Insert a new report into the local database.
	 *
	 * @return void
	 */
	private function initialLocalInsert() {
		// insert into local db tables
		$db = CP::getDb( DB_PRIMARY );
		$db->insert(
			'user_board_report_archives',
			[
				'ra_comment_id' => $this->data['comment']['cid'],
				'ra_last_edited' => $this->data['comment']['last_touched'] !== null ?
					date( 'Y-m-d H:i:s', $this->data['comment']['last_touched'] ) : null,
				'ra_user_id_from' => $this->data['comment']['author'],
				'ra_comment_text' => $this->data['comment']['text'],
				'ra_first_reported' => $this->data['first_reported'] !== null ?
					date( 'Y-m-d H:i:s', $this->data['first_reported'] ) : null,
				'ra_action_taken' => $this->data['action_taken'],
				'ra_action_taken_by_user_id' => $this->data['action_taken_by'],
				'ra_action_taken_at' => $this->data['action_taken_at'] !== null ?
					date( 'Y-m-d H:i:s', $this->data['action_taken_at'] ) : null
			],
			__METHOD__
		);
		$this->id = $db->insertId();
	}

	/**
	 * Get the unique key identifying this reported comment.
	 *
	 * @return string
	 */
	public function reportKey() {
		// Generating a dumb MD5 for backwards capatibility and not breaking old reports.
		return sprintf(
			'%s:%s:%s',
			md5( $this->data['comment']['cid'] ),
			$this->data['comment']['cid'],
			$this->data['comment']['last_touched']
		);
	}

	/**
	 * Add a new report to comment that has already been archived.
	 *
	 * @param User $fromUser The User reporting this comment
	 *
	 * @return void
	 */
	private function addReportFrom( User $fromUser ) {
		$commentAuthor = MediaWikiServices::getInstance()->getUserFactory()
			->newFromId( $this->data['comment']['author'] );

		if ( !isset( $this->id ) || $fromUser->isAnon() ) {
			// Can't add to a comment that hasn't been archived yet.
			return;
		}

		$newReport = [
			'reporter' => $fromUser->getId(),
			'timestamp' => time(),
		];

		// Add new report row to the local database.
		$db = CP::getDb( DB_PRIMARY );
		$db->insert(
			'user_board_reports',
			[
				'ubr_report_archive_id' => $this->id,
				'ubr_reporter_user_id' => $fromUser->getId(),
				'ubr_reported' => date( 'Y-m-d H:i:s', $newReport['timestamp'] )
			],
			__METHOD__
		);

		$toLocalUsers = [];
		$toLocalUsersObject = User::findUsersByGroup( [ 'sysop' ] );
		foreach ( $toLocalUsersObject as $user ) {
			if ( $user ) {
				$toLocalUsers[] = $user;
			}
		}

		$fromUserTitle = Title::makeTitle( NS_USER_PROFILE, $fromUser->getName() );
		$canonicalUrl = SpecialPage::getTitleFor( 'CommentModeration/' . $this->data['comment']['cid'] )->getFullURL();
		$broadcast = MediaWikiServices::getInstance()->getService( NotificationFactory::class )->newMulti(
			'user-moderation-profile-comment-report',
			$fromUser,
			$toLocalUsers,
			[
				'url' => $canonicalUrl,
				'message' => [
					[
						'user_note',
						''
					],
					[
						1,
						$fromUserTitle->getFullURL()
					],
					[
						2,
						$fromUser->getName()
					],
					[
						3,
						$canonicalUrl
					],
					[
						4,
						$commentAuthor->getName()
					]
				]
			]
		);
		if ( $broadcast ) {
			$broadcast->transmit();
		}
	}

	/**
	 * Dismiss or delete a reported comment.
	 *
	 * @param string $action Action to take on the reported comment. either 'delete' or 'dismiss'
	 * @param User $actor [Optional] User object of the acting user, defaults to|null $wgUser
	 *
	 * @return bool True if successful
	 */
	public function resolve( string $action, User $actor ) {
		if ( $this->data['action_taken'] ) {
			return false;
		}

		// update internal data
		$this->data['action_taken'] = ( $action === 'delete' ? self::ACTION_DELETE : self::ACTION_DISMISS );
		$this->data['action_taken_by'] = $actor->getId();
		$this->data['action_taken_at'] = time();

		// update data stores
		$comment = Comment::newFromId( $this->data['comment']['cid'] );
		return ( $action === 'dismiss' || CommentBoard::removeComment( $comment, $actor ) )
			&& $this->resolveInDb();
	}

	/**
	 * Marks a report as archived in the local database
	 *
	 * @return bool Success
	 */
	private function resolveInDb() {
		// write 1 or 2 to ra_action_taken column
		$db = CP::getDb( DB_PRIMARY );
		$result = $db->update(
			'user_board_report_archives',
			[
				'ra_action_taken' => $this->data['action_taken'],
				'ra_action_taken_by_user_id' => $this->data['action_taken_by'],
				'ra_action_taken_at' => date( 'Y-m-d H:i:s', $this->data['action_taken_at'] ),
			],
			[
				'ra_comment_id' => intval( $this->data['comment']['cid'] ),
				'ra_last_edited' => date( 'Y-m-d H:i:s', $this->data['comment']['last_touched'] )
			],
			__METHOD__
		);
		return $result;
	}
}

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

use CentralIdLookup;
use Cheevos\Cheevos;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use Exception;
use ManualLogEntry;
use Reverb\Notification\NotificationBroadcast;
use Sanitizer;
use SpecialPage;
use Title;
use User;

/**
 * Class that manages a 'wall' of comments on a user profile page
 */
class CommentBoard {
	/**
	 *	@var int		the id of the user to whom this comment board belongs to
	 */
	private $user_id;

	// maximum character length of a single comment
	const MAX_LENGTH = 5000;

	/**
	 * @var int		the number of comments to load on a board before a user clicks for more
	 */
	protected static $commentsPerPage = 5;

	/**
	 * @var int		one of the below constants
	 */
	public $type;

	/**
	 * Board type constants
	 */
	const BOARDTYPE_RECENT   = 1; // recent comments shown on a person's profile
	const BOARDTYPE_ARCHIVES = 2; // archive page that shows all comments

	/**
	 * Message visibility constants
	 */
	const DELETED_MESSAGE = -1;
	const PUBLIC_MESSAGE = 0;
	const PRIVATE_MESSAGE = 1;

	/**
	 * The user passed to the constructor is used as the main user from which the
	 * perspective of the SENT/RECEIVED status are determined.
	 *
	 * @param int	the ID of a user
	 */
	public function __construct($user_id, $type = self::BOARDTYPE_RECENT) {
		$this->DB = CP::getDb(DB_MASTER);
		$this->user_id = intval($user_id);
		$this->type = intval($type);
		if ($this->user_id < 1) {
			throw new Exception('Invalid user ID');
		}
	}

	/**
	 * Returns a sql WHERE clause fragment limiting comments to the current user's visibility
	 *
	 * @access private
	 * @param  object  [Optional] mw User object doing the viewing (defaults to wgUser)
	 * @return string  a single SQL condition entirely enclosed in parenthesis
	 */
	private static function visibleClause($asUser = null) {
		if (is_null($asUser)) {
			global $wgUser;
			$asUser = $wgUser;
		} else {
			$asUser = User::newFromId($asUser);
		}

		if ($asUser->isAllowed('profile-moderate')) {
			// admins see everything
			return '1=1';
		} else {
			$conditions = [];
			// Everyone sees public messages.
			$conditions[] = 'user_board.ub_type = 0';
			// See private if you are author or recipient.
			$conditions[] = sprintf('user_board.ub_type = 1 AND (user_board.ub_user_id = %1$s OR user_board.ub_user_id_from = %1$s)', $asUser->getId());
			// See deleted if you are the author.
			$conditions[] = sprintf('user_board.ub_type = -1 AND user_board.ub_user_id_from = %1$s', $asUser->getId());
			return '( (' . implode(') OR (', $conditions) . ') )';
		}
	}

	/**
	 * Returns the total number of top-level comments (or replies to a given comment) that have been left
	 *
	 * @param int|null $inReplyTo [Optional] id of a comment (changes from a top-level count to a reply count)
	 * @param int|null $asUser    [Optional] user ID of a user viewing (defaults to wgUser)
	 */
	public function countComments($inReplyTo = null, $asUser = null) {
		if (is_null($inReplyTo)) {
			$inReplyTo = 0;
		} else {
			$inReplyTo = intval($inReplyTo);
		}

		$db = CP::getDb(DB_REPLICA);
		$results = $db->select(
			['user_board'],
			['count(*) as total'],
			[
				self::visibleClause($asUser),
				'ub_in_reply_to'	=> $inReplyTo,
				'ub_user_id'		=> $this->user_id
			],
			__METHOD__
		);

		$row = $results->fetchRow();
		return $row['total'];
	}

	/**
	 * Look up a single comment given a comment id (for display from a permalink)
	 *
	 * @param  int      $commentId  id of a user board comment
	 * @param  bool     $withParent [Optional] true by default, if given ID is a reply, will fetch parent comment as well
	 * @param  int|null $asUser     [Optional] user ID of user viewing (defaults to wgUser)
	 * @return array An array of comment data in the same format as getComments.
	 *   array will be empty if comment is unknown, or not visible.
	 */
	public static function getCommentById($commentId, $withParent = true, $asUser = null) {
		$commentId = intval($commentId);
		if ($commentId < 1) {
			return [];
		}

		// Look up the target comment.
		$comment = self::queryCommentById($commentId);

		if (empty($comment)) {
			return [];
		}

		// Switch our primary ID a parent comment, if it exists.
		if ($withParent && $comment['ub_in_reply_to']) {
			$rootId = $comment['ub_in_reply_to'];
		} else {
			$rootId = $commentId;
		}

		if (!self::canView($comment, $asUser)) {
			return [];
		}

		$board = new self($comment['ub_user_id'], self::BOARDTYPE_ARCHIVES);
		$comment = $board->getCommentsWithConditions(['ub_id' => $rootId], $asUser, 0, 1);
		// force loading all replies instead of just 5
		$comment[0]['replies'] = $board->getReplies($rootId, $asUser, 0);
		return $comment;
	}

	/**
	 * Get a raw comment from the database by ID.
	 *
	 * @access private
	 * @param  int $commentId Comment ID
	 * @return mixed	Database result or false.
	 */
	private static function queryCommentById($commentId) {
		$DB = CP::getDb(DB_MASTER);
		$result = $DB->select(
			['user_board'],
			['*'],
			['ub_id' => intval($commentId)],
			__METHOD__
		);

		return $result->fetchRow();
	}

	/**
	 * Generic comment retrieval utility function.  Automatically limits to viewable types.
	 *
	 * @access private
	 * @param  array $conditions SQL conditions applied to the user_board table query.  Will be merged with existing conditions.
	 * @param  int   $asUser     [Optional] User ID of user viewing. (Defaults to wgUser)
	 * @param  int   $startAt    [Optional] Number of comments to skip when loading more.
	 * @param  int   $limit      [Optional] Number of top-level items to return.
	 * @return array	comments!
	 */
	private function getCommentsWithConditions($conditions, $asUser = null, $startAt = 0, $limit = 100) {
		if (!is_array($conditions)) {
			$conditions = [];
		}
		// Fetch top level comments.
		$results = $this->DB->select(
			['user_board'],
			[
				'*',
				'IFNULL(ub_last_reply, ub_date) AS last_updated'
			],
			array_merge([
				self::visibleClause($asUser),
			], $conditions),
			__METHOD__,
			[
				'ORDER BY'	=> 'last_updated DESC',
				'OFFSET'	=> $startAt,
				'LIMIT'		=> $limit
			]
		);

		$comments = [];
		$commentIds = []; // will contain a mapping of commentId => array index within $comments
		// (for fast lookup of a comment by id when inserting replies)
		while ($row = $results->fetchRow()) {
			$commentIds[$row['ub_id']] = count($comments);
			$row['reply_count'] = 0;
			$comments[] = $row;
		}

		if (empty($comments)) {
			return $comments;
		}

		// Count many replies each comment in this chunk has.
		$results = $this->DB->select(
			['user_board'],
			[
				'ub_in_reply_to AS ub_id',
				'COUNT(*) as replies'
			],
			[
				'ub_in_reply_to' => array_keys($commentIds)
			],
			__METHOD__,
			[
				'GROUP BY'	=> 'ub_in_reply_to'
			]
		);
		// @TODO: fetch replies for all comments in a single DB query?
		while ($row = $results->fetchRow()) {
			$comments[$commentIds[$row['ub_id']]]['reply_count'] = intval($row['replies']);
			// retrieve replies if there are any
			if ($row['replies'] > 0) {
				$comments[$commentIds[$row['ub_id']]]['replies'] = $this->getReplies($row['ub_id'], $asUser);
			}
		}

		return $comments;
	}

	/**
	 * Gets all comments on the board.
	 *
	 * @access public
	 * @param  int|null $asUser  [Optional] user ID of user viewing (defaults to wgUser)
	 * @param  int      $startAt [Optional] number of comments to skip when loading more
	 * @param  int      $limit   [Optional] number of top-level items to return
	 * @param  int      $maxAge  [Optional] maximum age of comments (by number of days)
	 * @return array	an array of comment data (text and user info)
	 */
	public function getComments($asUser = null, $startAt = 0, $limit = 100, $maxAge = 30) {
		$searchConditions = [
			'ub_in_reply_to'	=> 0,
			'ub_user_id'		=> $this->user_id
		];
		if ($maxAge >= 0) {
			$searchConditions[] = 'IFNULL(ub_last_reply, ub_date) >= ' . $this->DB->addQuotes(date('Y-m-d H:i:s', time() - $maxAge * 86400));
		}
		return $this->getCommentsWithConditions($searchConditions, $asUser, $startAt, $limit);
	}

	/**
	 * Gets all replies to a given comment
	 *
	 * @access public
	 * @param  int      $rootComment id of a comment that would be replied to
	 * @param  int|null $asUser      [Optional] user ID of user viewing (defaults to wgUser)
	 * @param  int      $limit       [Optional] max number items to return (older replies will be ommitted)
	 * @return array	array of reply data
	 */
	public function getReplies($rootComment, $asUser = null, $limit = 5) {
		// Fetch comments.
		$options = [
			'ORDER BY'	=> 'ub_date DESC'
		];
		if ($limit > 0) {
			$options['LIMIT'] = intval($limit);
		}
		$results = $this->DB->select(
			['user_board'],
			[
				'*',
			],
			[
				self::visibleClause($asUser),
				'ub_in_reply_to'	=> $rootComment,
				'ub_user_id'		=> $this->user_id
			],
			__METHOD__,
			$options
		);

		$comments = [];
		while ($row = $results->fetchRow()) {
			$comments[] = $row;
		}

		return array_reverse($comments);
	}

	/**
	 * Checks if a user should be able to view a specific comment
	 *
	 * @access public
	 * @param  mixed       $commentId int id of comment to check, or array row from user_board table
	 * @param  object|null $user      [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canView($commentId, $user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}
		// Early check for admin status.
		if ($user->isAllowed('profile-moderate')) {
			return true;
		}

		if (is_array($commentId)) {
			$comment = $commentId;
		} else {
			$comment = self::queryCommentById($commentId);
		}

		// PUBLIC comments visible to all, DELETED comments visible to the author, PRIVATE to author and recipient.
		return $comment['ub_type'] == self::PUBLIC_MESSAGE
			|| ($comment['ub_type'] == self::PRIVATE_MESSAGE && $comment['ub_user_id'] == $user->getId() && $comment['ub_user_id_from'] == $user->getId())
			|| ($comment['ub_type'] == self::DELETED_MESSAGE && $comment['ub_user_id_from'] == $user->getId());
	}

	/**
	 * Checks if a user has permissions to leave a comment.
	 *
	 * @access public
	 * @param  object      $toUser   int user ID or User object who owns the potential board.
	 * @param  object|null $fromUser [Optional] User object for comment author, defaults to|null $wgUser
	 * @return boolean	Can Comment
	 */
	public static function canComment($toUser, $fromUser = null) {
		global $wgCPEditsToComment, $wgEmailAuthentication;

		if (is_numeric($toUser)) {
			$toUser = User::newFromId($toUser);
		}
		if (empty($toUser)) {
			return false;
		}
		if (is_null($fromUser)) {
			global $wgUser;
			$fromUser = $wgUser;
		}

		$editCount = $fromUser->getEditCount();

		$noEmailAuth = ($wgEmailAuthentication && (!boolval($fromUser->getEmailAuthenticationTimestamp()) || !Sanitizer::validateEmail($fromUser->getEmail())));

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($fromUser, CentralIdLookup::AUDIENCE_RAW);

		if ($globalId > 0) {
			try {
				$stats = Cheevos::getStatProgress(
					[
						'user_id'	=> $globalId,
						'global'	=> true,
						'stat'		=> 'article_edit'
					]
				);
				$stats = CheevosHelper::makeNiceStatProgressArray($stats);
				$editCount = (isset($stats[$globalId]['article_edit']['count']) && $stats[$globalId]['article_edit']['count'] > $editCount ? $stats[$globalId]['article_edit']['count'] : $editCount);
			} catch (CheevosException $e) {
				wfDebug("Encountered Cheevos API error getting article_edit count.");
			}
		}

		// User must be logged in, must not be blocked, and target must not be blocked (with exception for admins).
		return !$noEmailAuth && $fromUser->isLoggedIn() && !$fromUser->isBlocked() && (($editCount >= $wgCPEditsToComment && !$toUser->isBlocked()) || $fromUser->isAllowed('block'));
	}

	/**
	 * Add a public comment to the board
	 *
	 * @access public
	 * @param  string   $commentText Comment Text
	 * @param  int|null $fromUser    [Optional] User ID of user posting (defaults to wgUser)
	 * @param  int|null $inReplyTo   [Optional] ID of a board post that this will be in reply to
	 * @return int	ID of the newly created comment, or 0 for failure
	 */
	public function addComment($commentText, $fromUser = null, $inReplyTo = null) {
		$commentText = substr(trim($commentText), 0, self::MAX_LENGTH);
		if (empty($commentText)) {
			return false;
		}
		$dbw = CP::getDb(DB_MASTER);

		$toUser = User::newFromId($this->user_id);
		if (is_null($fromUser)) {
			global $wgUser;
			$fromUser = $wgUser;
		} else {
			$fromUser = User::newFromId(intval($fromUser));
		}
		if (!self::canComment($toUser, $fromUser)) {
			return false;
		}

		if (is_null($inReplyTo)) {
			$inReplyTo = 0;
		} else {
			$inReplyTo = intval($inReplyTo);
			$parentComment = self::queryCommentById($inReplyTo);
			if (isset($parentComment['ub_user_id_from'])) {
				$parentCommenter = User::newFromId($parentComment['ub_user_id_from']);
			}
		}

		$success = $dbw->insert(
			'user_board',
			[
				'ub_in_reply_to' => $inReplyTo,
				'ub_user_id_from' => $fromUser->getId(),
				'ub_user_name_from' => $fromUser->getName(),
				'ub_user_id' => $this->user_id,
				'ub_user_name' => $toUser->getName(),
				'ub_message' => $commentText,
				'ub_type' => self::PUBLIC_MESSAGE,
				'ub_date' => date('Y-m-d H:i:s'),
			],
			__METHOD__
		);

		if ($success) {
			$newCommentId = $dbw->insertId();
		} else {
			$newCommentId = 0;
		}

		if ($newCommentId) {
			$action = 'created';

			if ($inReplyTo) {
				$dbw->update(
					'user_board',
					[
						'ub_last_reply' => date('Y-m-d H:i:s')
					],
					['ub_id = ' . $inReplyTo],
					__METHOD__
				);
				$action = 'replied';
			}

			wfRunHooks('CurseProfileAddComment', [$fromUser, $toUser, $inReplyTo, $commentText]);
			if ($inReplyTo) {
				wfRunHooks('CurseProfileAddCommentReply', [$fromUser, $toUser, $inReplyTo, $commentText]);
			}

			$toUserTitle = Title::makeTitle(NS_USER_PROFILE, $toUser->getName());
			$commentPermanentLink = SpecialPage::getTitleFor('CommentPermalink', $newCommentId, 'comment' . $newCommentId)->getFullURL();
			$userNote = substr($commentText, 0, 80);
			if ($toUser->getId() != $fromUser->getId()) {
				if ($inReplyTo > 0) {
					if (!$parentCommenter->equals($toUser)) {
						// We have to make two notifications.  One for the profile owner and one for the parent commenter.
						$broadcast = NotificationBroadcast::newSingle(
							'user-interest-profile-comment-reply-other-self',
							$fromUser,
							$toUser,
							[
								'url' => $commentPermanentLink,
								'message' => [
									[
										'user_note',
										$userNote
									],
									[
										1,
										$fromUser->getName()
									],
									[
										2,
										$toUser->getName()
									],
									[
										3,
										$parentCommenter->getName()
									]
								]
							]
						);
						if ($broadcast) {
							$broadcast->transmit();
						}
					}
					$broadcast = NotificationBroadcast::newSingle(
						'user-interest-profile-comment-reply-self-'.($parentCommenter->equals($toUser) ? 'self' : 'other'),
						$fromUser,
						$parentCommenter,
						[
							'url' => $commentPermanentLink,
							'message' => [
								[
									'user_note',
									$userNote
								],
								[
									1,
									$fromUser->getName()
								],
								[
									2,
									$toUser->getName()
								],
								[
									3,
									$parentCommenter->getName()
								]
							]
						]
					);
					if ($broadcast) {
						$broadcast->transmit();
					}
				} else {
					$broadcast = NotificationBroadcast::newSingle(
						'user-interest-profile-comment',
						$fromUser,
						$toUser,
						[
							'url' => $commentPermanentLink,
							'message' => [
								[
									'user_note',
									$userNote
								],
								[
									1,
									$fromUser->getName()
								],
								[
									2,
									$toUserTitle->getFullText()
								]
							]
						]
					);
					if ($broadcast) {
						$broadcast->transmit();
					}
				}
			}

			// Insert an entry into the Log.
			$log = new ManualLogEntry('curseprofile', 'comment-' . $action);
			$log->setPerformer($fromUser);
			$log->setTarget($toUserTitle);
			$log->setComment(null);
			$log->setParameters(
				[
					'4:comment_id' => $newCommentId
				]
			);
			$logId = $log->insert();
			$log->publish($logId);
		}

		return $newCommentId;
	}

	/**
	 * Checks if a user has permissions to reply to a comment
	 *
	 * @param  mixed       $commentId int id of comment to check, or array row from user_board table
	 * @param  object|null $user      [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canReply($commentId, $user = null) {
		global $wgCPEditsToComment;

		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_array($commentId)) {
			$comment = $commentId;
		} else {
			$comment = self::queryCommentById($commentId);
		}

		$boardOwner = User::newFromId($comment['ub_user_id']);

		// comment must not be deleted and user must be logged in
		return $comment['ub_type'] > self::DELETED_MESSAGE && self::canComment($boardOwner, $user);
	}

	/**
	 * Replaces the text content of a comment. Permissions are not checked. Use canEdit() to check.
	 *
	 * @param  int    $commentId id of a user board comment
	 * @param  string $message   new text to use for the comment
	 * @return bool	true if successful
	 */
	public static function editComment($commentId, $message) {
		global $wgUser;

		$DB = CP::getDb(DB_MASTER);
		$commentId = intval($commentId);

		// Preparing stuff for the Log Entry
		$comment = self::getCommentById($commentId);
		$toUser = User::newFromId($comment[0]['ub_user_id']);
		$title = Title::newFromURL('UserProfile:' . $toUser->getName());
		$fromUser = $wgUser;

		$log = new ManualLogEntry('curseprofile', 'comment-edited');
		$log->setPerformer($fromUser);
		$log->setTarget($title);
		$log->setComment(null);
		$log->setParameters(
			[
				'4:comment_id' => $commentId
			]
		);
		$logId = $log->insert();
		$log->publish($logId);

		return $DB->update(
			'user_board',
			[
				'ub_message' => $message,
				'ub_edited' => date('Y-m-d H:i:s'),
			],
			['ub_id' => $commentId],
			__METHOD__
		);
	}

	/**
	 * Checks if a user has permissions to edit a comment
	 *
	 * @param  mixed       $commentId int id of comment to check, or array row from user_board table
	 * @param  object|null $user      [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canEdit($commentId, $user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_array($commentId)) {
			$comment = $commentId;
		} else {
			$comment = self::queryCommentById($commentId);
		}

		// comment must not be deleted and must be written by this user
		return $comment['ub_type'] > self::DELETED_MESSAGE && $comment['ub_user_id_from'] == $user->getId();
	}

	/**
	 * Remove a comment from the board. Permissions are not checked. Use canRemove() to check.
	 * TODO: if comment is a reply, update the parent's ub_last_reply field (would that behavior be too surprising?)
	 *
	 * @param  int         $commentId ID of the comment to remove.
	 * @param  int|null    $user      [Optional] User object of the admin acting, defaults to|null $wgUser
	 * @param  string|null $time      [Optional] Timestamp in the format of date('Y-m-d H:i:s').
	 * @return mixed	$db->update return or false on error.
	 */
	public static function removeComment($commentId, $user = null, $time = null) {
		if (!($user instanceof \User)) {
			global $wgUser;

			$user = $wgUser;
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);

		if (!$globalId) {
			return false;
		}

		if (!$time) {
			$time = date('Y-m-d H:i:s');
		}

		// Preparing stuff for the Log Entry
		$comment = self::getCommentById($commentId);
		$toUser = User::newFromId($comment[0]['ub_user_id']);
		$title = Title::makeTitle(NS_USER_PROFILE, $toUser->getName());

		$log = new ManualLogEntry('curseprofile', 'comment-deleted');
		$log->setPerformer($user);
		$log->setTarget($title);
		$log->setComment(null);
		$log->setParameters(
			[
				'4:comment_id' => $commentId
			]
		);
		$logId = $log->insert();
		$log->publish($logId);

		$db = CP::getDb(DB_MASTER);
		return $db->update(
			'user_board',
			[
				'ub_type'			=> self::DELETED_MESSAGE,
				'ub_admin_acted'	=> $globalId,
				'ub_admin_acted_at'	=> $time
			],
			['ub_id' => $commentId]
		);
	}

	/**
	 * Checks if a user has permissions to remove a comment
	 *
	 * @param  mixed       $commentId int id of comment to check, or array row from user_board table
	 * @param  object|null $user      [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canRemove($commentId, $user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_array($commentId)) {
			$comment = $commentId;
		} else {
			$comment = self::queryCommentById($commentId);
		}

		// user must not be blocked, comment must either be authored by current user or on user's profile
		return $comment['ub_type'] != self::DELETED_MESSAGE && !$user->isBlocked() &&
			($comment['ub_user_id'] == $user->getId()
				|| (isset($comment['ub_user_from_id']) && intval($comment['ub_user_from_id']) === $user->getId())
				|| $user->isAllowed('profile-moderate'));
	}

	/**
	 * Restore a comment to the board. Permissions are not checked. Use canRemove() to check.
	 * TODO: if comment is a reply, update the parent's ub_last_reply field (would that behavior be too surprising?)
	 *
	 * @param  int $commentId id of a comment to remove
	 * @return mixed	whatever DB->update() returns
	 */
	public static function restoreComment($commentId) {
		$db = CP::getDb(DB_MASTER);
		return $db->update(
			'user_board',
			[
				'ub_type' => self::PUBLIC_MESSAGE,
				'ub_admin_acted' => null,
				'ub_admin_acted_at' => null,
			],
			['ub_id=' . intval($commentId)]
		);
	}

	/**
	 * Checks if a user has permissions to restore a deleted comment
	 *
	 * @param  mixed       $commentId int id of comment to check, or array row from user_board table
	 * @param  object|null $user      [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canRestore($commentId, $user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_array($commentId)) {
			$comment = $commentId;
		} else {
			$comment = self::queryCommentById($commentId);
		}

		// comment must be deleted, user has mod permissions or was the original author and deleter
		return $comment['ub_type'] == self::DELETED_MESSAGE &&
			($user->isAllowed('profile-moderate')
				|| $comment['ub_user_id'] == $user->getId() && $comment['ub_admin_acted'] == $user->getId());
	}

	/**
	 * Permanently remove a comment from the board. Permissions are not checked. Use canPurge() to check.
	 *
	 * @param  int $commentId id of a comment to remove
	 * @return mixed	whatever DB->delete() returns
	 */
	public static function purgeComment($commentId) {
		global $wgUser;

		$user = $wgUser;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);

		if (!$globalId) {
			return false;
		}

		// Preparing stuff for the Log Entry
		$comment = self::getCommentById($commentId);
		$toUser = User::newFromId($comment[0]['ub_user_id']);
		$title = Title::makeTitle(NS_USER_PROFILE, $toUser->getName());

		$log = new ManualLogEntry('curseprofile', 'comment-deleted');
		$log->setPerformer($user);
		$log->setTarget($title);
		$log->setComment(null);
		$logId = $log->insert();
		$log->publish($logId);

		$db = CP::getDb(DB_MASTER);
		return $db->delete(
			'user_board',
			['ub_id =' . intval($commentId)]
		);
	}

	/**
	 * Checks if a user has permissions to permanently comments
	 *
	 * @param  object|null $user [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canPurge($user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		} elseif (!is_a($user, 'User')) {
			return false;
		}

		// only Curse group has this right
		return $user->isAllowed('profile-purgecomments');
	}

	/**
	 * Send a comment to the moderation queue. Does not check permissions.
	 *
	 * @param  int $commentId id of the comment to report
	 * @return mixed	CommentReport instance or null for failure
	 */
	public static function reportComment($commentId) {
		if ($commentId) {
			return CommentReport::newUserReport($commentId);
		}
	}

	/**
	 * Checks if a user has permissions to report a comment
	 *
	 * @param  mixed       $commentId int id of comment to check, or array row from user_board table
	 * @param  object|null $user      [Optional] mw User object, defaults to|null $wgUser
	 * @return bool
	 */
	public static function canReport($commentId, $user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_array($commentId)) {
			$comment = $commentId;
		} else {
			$comment = self::queryCommentById($commentId);
		}

		// user must be logged-in to report and comment must be public (not deleted)
		return !$user->isAnon() && $comment['ub_user_id_from'] != $user->getId() && $comment['ub_type'] == self::PUBLIC_MESSAGE;
	}

	/**
	 * Filter text through abuse filters.
	 *
	 * @access private
	 * @param  string $text Text to check against abuse filters.
	 * @return boolean Passed abuse filters.
	 */
	private function checkAbuseFilters($text) {
		/*if (class_exists("AbuseFilterHooks") && method_exists("AbuseFilterHooks::filterEdit")) {
			$status = Status::newGood();

			AbuseFilterHooks::filterEdit($context, null, $text, $status, '', true);

			if (!$status->isOK()) {
				$msg = $status->getErrorsArray();
				$msg = $msg[0];

				// Use the error message key name as error code, the first parameter is the filter description.
				if ($msg instanceof Message) {
					// For forward compatibility: In case we switch over towards using Message objects someday.
					// (see the todo for AbuseFilter::buildStatus)
					$code = $msg->getKey();
					$filterDescription = $msg->getParams();
					$filterDescription = $filterDescription[0];
					$warning = $msg->parse();
				} else {
					$code = array_shift($msg);
					$filterDescription = $msg[0];
					$warning = wfMessage($code)->params($msg)->parse();
				}

				$result = [
					'code' => $code,
					'info' => 'Hit AbuseFilter: '.$filterDescription,
					'warning' => $warning
				];
			}

			return $status->isOK();
		}*/
	}
}

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
 * Class that manages a 'wall' of comments on a user profile page
 */
class CommentBoard {
	/**
	 *	@var	int		the id of the user to whom this comment board belongs to
	 */
	private $user_id;

	// maximum character length of a single comment
	const MAX_LENGTH = 5000;

	/**
	 * @var		int		the number of comments to load on a board before a user clicks for more
	 */
	protected static $commentsPerPage = 5;

	/**
	 * @var		int		one of the below constants
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
	 * @param	int		the ID of a user
	 */
	public function __construct($user_id, $type = self::BOARDTYPE_RECENT) {
		$this->user_id = intval($user_id);
		$this->type = intval($type);
		if ($this->user_id < 1) {
			throw new \Exception('Invalid user ID');
		}
	}

	/**
	 * Returns an array of visibility constants that should be visable for the given user id
	 *
	 * @param	int		ID of a user
	 * @return	array	INTs from the list of visibility CONST
	 */
	private function getViewableTypes($asUser) {
		$types = [self::PUBLIC_MESSAGE];

		if (is_null($asUser)) {
			global $wgUser;
			$asUser = $wgUser->getID();
		}
		if (!($asUser instanceof \User)) {
			$asUser = \User::newFromId($asUser);
			$asUser->load();
		}

		if ($this->user_id == $asUser->getId()) {
			$types[] = self::PRIVATE_MESSAGE;
		}

		if ($this->type == self::BOARDTYPE_ARCHIVES && $asUser->isAllowed('profile-modcomments')) {
			$types[] = self::DELETED_MESSAGE;
		}

		return $types;
	}

	/**
	 * Returns the total number of top-level comments (or replies to a given comment) that have been left
	 *
	 * @param	int		[optional] id of a comment (changes from a top-level count to a reply count)
	 * @param	int		[optional] user ID of a user viewing (defaults to wgUser)
	 */
	public function countComments($inReplyTo = null, $asUser = null) {
		if (is_null($inReplyTo)) {
			$inReplyTo = 0;
		} else {
			$inReplyTo = intval($inReplyTo);
		}
		$types = $this->getViewableTypes($asUser);

		$mouse = CP::loadMouse();
		$res = $mouse->DB->select([
			'select' => 'count(*) as count',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_type IN ('.implode(',',$types).') AND b.ub_in_reply_to = '.$inReplyTo.' AND b.ub_user_id = '.$this->user_id,
		]);

		$row = $mouse->DB->fetch($res);
		return $row['count'];
	}

	/**
	 * Look up a single comment given a comment id (for display from a permalink)
	 *
	 * @param	int		id of a user board comment
	 * @param	int		[optional] user ID of user viewing (defaults to wgUser)
	 * @return	array	an array of comment data in the same format as getComments.
	 *   array will be empty if comment is unknown, or not visible.
	 */
	public static function getCommentById($comment_id, $asUser = null) {
		$comment_id = intval($comment_id);
		if ($comment_id < 1) {
			return [];
		}

		$db = wfGetDB(DB_SLAVE);

		// Look up the target comment
		$res = $db->select(
			['user_board'],
			['ub_in_reply_to', 'ub_user_id', 'ub_type'],
			["ub_id = $comment_id"],
			__METHOD__
		);
		$comment = $res->fetchRow();
		$res->free();

		if (!$comment) {
			return [];
		}

		// switch our primary ID a parent comment, if it exists
		if ($comment['ub_in_reply_to']) {
			$rootId = $comment['ub_in_reply_to'];
		} else {
			$rootId = $comment_id;
		}

		$board = new self($comment['ub_user_id'], self::BOARDTYPE_ARCHIVES);
		$types = $board->getViewableTypes($asUser);

		if (!in_array($comment['ub_type'], $types)) {
			return [];
		}

		$comment = $board->getCommentsWithConditions("AND ub_id = $rootId", $asUser, 0, 1);
		// force loading all replies instead of just 5
		$comment[0]['replies'] = $board->getReplies($rootId, $asUser, 0);
		return $comment;
	}

	/**
	 * Generic comment retrieval utility function. Automatically limits to viewable types.
	 *
	 * @param	string	sql conditions applied to the user_board table query
	 * @param	int		[optional] user ID of user viewing (defaults to wgUser)
	 * @param	int		[optional] number of comments to skip when loading more
	 * @param	int		[optional] number of top-level items to return
	 * @return	array	comments!
	 */
	private function getCommentsWithConditions($conditions, $asUser = null, $startAt = 0, $limit = 100) {
		$mouse = CP::loadMouse();

		$types = $this->getViewableTypes($asUser);

		// Fetch top level comments
		$res = $mouse->DB->select([
			'select' => 'b.*',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_type IN ('.implode(',',$types).') '.$conditions,
			'order'  => 'b.ub_date DESC',
			'limit'  => [$startAt, $limit],
		]);
		$comments = [];
		$commentIds = []; // will contain a mapping of commentId => array index within $comments
		// (for fast lookup of a comment by id when inserting replies)
		while ($row = $mouse->DB->fetch($res)){
			$commentIds[$row['ub_id']] = count($comments);
			$row['reply_count'] = 0;
			$comments[] = $row;
		}

		if (empty($comments)) {
			return $comments;
		}

		// Count many replies each comment in this chunk has
		$repliesRes = $mouse->DB->select([
			'select' => 'b.ub_in_reply_to as ub_id, COUNT(*) as replies',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_in_reply_to IN ('.implode(',',array_keys($commentIds)).')',
			'group'  => 'b.ub_in_reply_to',
		]);
		while ($row = $mouse->DB->fetch($repliesRes)) {
			$comments[$commentIds[$row['ub_id']]]['reply_count'] = intval($row['replies']);
			// retrieve replies if there are any
			if ($row['replies'] > 0) {
				$comments[$commentIds[$row['ub_id']]]['replies'] = $this->getReplies($row['ub_id'], $asUser);
			}
		}

		return $comments;
	}

	/**
	 * Gets all comments on the board
	 * TODO: fetch some replies and them in to the array along with counts of how many more there were left to load
	 *
	 * @param	int		[optional] user ID of user viewing (defaults to wgUser)
	 * @param	int		[optional] number of comments to skip when loading more
	 * @param	int		[optional] number of top-level items to return
	 * @param	int		[optional] maximum age of comments (by number of days)
	 * @return	array	an array of comment data (text and user info)
	 */
	public function getComments($asUser = null, $startAt = 0, $limit = 100, $maxAge = 30) {
		$searchConditions = ' AND b.ub_in_reply_to = 0 AND b.ub_user_id = '.$this->user_id;
		if ($maxAge >= 0) {
			$searchConditions .= ' AND b.ub_date >= "'.date('Y-m-d H:i:s', time()-$maxAge*86400).'"';
		}
		return $this->getCommentsWithConditions($searchConditions, $asUser, $startAt, $limit);
	}

	/**
	 * Gets all replies to a given comment
	 *
	 * @param	int		id of a comment that would be replied to
	 * @param	int		[optional] user ID of user viewing (defaults to wgUser)
	 * @param	int		[optional] max number items to return (older replies will be ommitted)
	 * @return	array	array of reply data
	 */
	public function getReplies($rootComment, $asUser = null, $limit = 5) {
		$types = $this->getViewableTypes($asUser);
		$mouse = CP::loadMouse();

		// Fetch comments
		$selOpt = [
			'select' => 'b.*',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_type IN ('.implode(',',$types).') AND b.ub_in_reply_to = '.$rootComment.' AND b.ub_user_id = '.$this->user_id,
			'order'  => 'b.ub_date DESC',
		];
		if ($limit > 0) {
			$selOpt['limit'] = [intval($limit)];
		}
		$res = $mouse->DB->select($selOpt);
		$comments = [];
		while ($row = $mouse->DB->fetch($res)){
			$comments[] = $row;
		}

		return array_reverse($comments);
	}

	/**
	 * Add a public comment to the board
	 *
	 * @access	public
	 * @param	integer	the ID of a user
	 * @param	integer	optional user ID of user posting (defaults to wgUser)
	 * @param	integer	optional id of a board post that this will be in reply to
	 * @return	boolean	Success
	 */
	public function addComment($commentText, $fromUser = null, $inReplyTo = null) {
		$commentText = substr(trim($commentText), 0, self::MAX_LENGTH);
		if (empty($commentText)) {
			return false;
		}
		$dbw = wfGetDB( DB_MASTER );

		$toUser = \User::newFromId($this->user_id);
		if (is_null($fromUser)) {
			global $wgUser;
			$fromUser = $wgUser;
		} else {
			$fromUser = \User::newFromId(intval($fromUser));
		}
		if ($fromUser->isBlocked()) {
			return false;
		}

		if (is_null($inReplyTo)) {
			$inReplyTo = 0;
		} else {
			$inReplyTo = intval($inReplyTo);
		}

		$dbw->insert(
			'user_board',
			array(
				'ub_in_reply_to' => $inReplyTo,
				'ub_user_id_from' => $fromUser->getId(),
				'ub_user_name_from' => $fromUser->getName(),
				'ub_user_id' => $this->user_id,
				'ub_user_name' => $toUser->getName(),
				'ub_message' => $commentText,
				'ub_type' => self::PUBLIC_MESSAGE,
				'ub_date' => date( 'Y-m-d H:i:s' ),
			),
			__METHOD__
		);

		wfRunHooks('CurseProfileAddComment', [$fromUser, $this->user_id, $inReplyTo, $commentText]);

		if ($toUser->getId() != $fromUser->getId()) {
			\EchoEvent::create([
				'type' => 'profile-comment',
				'agent' => $fromUser,
				// 'title' => $toUser->getUserPage(),
				'extra' => [
					'target_user_id' => $toUser->getId(),
					'comment_text' => substr($commentText, 0, NotificationFormatter::MAX_PREVIEW_LEN),
				]
			]);
		}
		return true;
	}

	/**
	 * Remove a comment from the board. Permissions are not checked. Use canRemove() to check.
	 *
	 * @param	int		id of a comment to remove
	 * @return	stuff	whatever mouse DB returns
	 */
	public static function removeComment($comment_id) {
		$mouse = CP::loadMouse();
		return $mouse->DB->update('user_board', ['ub_type' => self::DELETED_MESSAGE], 'ub_id ='.intval($comment_id));
	}

	/**
	 * Checks if a user has permissions to remove a comment
	 *
	 * @param	mixed	int id of comment to check, or array row from user_board table
	 * @param	obj		[optional] mw User object, defaults to $wgUser
	 * @return	bool
	 */
	public static function canRemove($comment_id, $user = null) {
		if (is_null($user)) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_array($comment_id)) {
			$comment = $comment_id;
		} else {
			$mouse = CP::loadMouse();
			$comment = $mouse->DB->selectAndFetch([
				'select' => 'b.*',
				'from'   => ['user_board' => 'b'],
				'where'  => 'b.ub_id = '.intval($comment_id),
			]);
		}

		// comment must either be on user's own profile, or have the permission
		return $comment['ub_type'] != self::DELETED_MESSAGE && 
			($comment['ub_user_id'] == $user->getId() || $user->isAllowed('profile-modcomments'));
	}
}

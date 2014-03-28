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
	private $user_id;

	/**
	 * @var		int		the number of comments to load on a board before a user clicks for more
	 */
	protected static $commentsPerPage = 5;

	/**
	 * Message visibility constants
	 */
	const PUBLIC_MESSAGE = 0;
	const PRIVATE_MESSAGE = 1;

	/**
	 * The user passed to the constructor is used as the main user from which the
	 * perspective of the SENT/RECEIVED status are determined.
	 *
	 * @param	int		the ID of a user
	 */
	public function __construct($user_id) {
		$this->user_id = intval($user_id);
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
	private function getViewableTypes($user_id) {
		$types = [self::PUBLIC_MESSAGE];
		if (is_null($asUser)) {
			global $wgUser;
			$asUser = $wgUser->getID();
		}
		if ($this->user_id == $asUser) {
			$types[] = self::PRIVATE_MESSAGE;
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
	 * Gets the comments on the board
	 * TODO: fetch some replies and them in to the array along with counts of how many more there were left to load
	 *
	 * @param	int		[optional] user ID of user viewing (defaults to wgUser)
	 * @param	int		[optional] number of comments to skip when loading more
	 * @param	int		[optional] number of top-level items to return
	 * @param	int		[optional] maximum age of comments (by number of days)
	 * @return	array	an array of comment data (text and user info)
	 */
	public function getComments($asUser = null, $startAt = 0, $limit = 100, $maxAge = 30) {
		$types = $this->getViewableTypes($asUser);
		$mouse = CP::loadMouse();

		if ($maxAge < 0) {
			$ageLimit = '';
		} else {
			$ageLimit = ' AND b.ub_date >= "'.date('Y-m-d H:i:s', time()-$maxAge*86400).'"';
		}

		// Fetch top level comments
		$res = $mouse->DB->select([
			'select' => 'b.*',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_type IN ('.implode(',',$types).') AND b.ub_in_reply_to = 0 AND b.ub_user_id = '.$this->user_id . $ageLimit,
			'order'  => 'b.ub_date DESC',
			'limit'  => [$startAt, $limit],
		]);
		$comments = [];
		$commentIds = []; // will contain a mapping of commentId => index within $comments
		while ($row = $mouse->DB->fetch($res)){
			$commentIds[$row['ub_id']] = count($comments);
			$row['reply_count'] = 0;
			$comments[] = $row;
		}

		if (empty($comments)) {
			return $comments;
		}

		// Fetch number of replies for each comment in this chunk
		$repliesRes = $mouse->DB->select([
			'select' => 'b.ub_in_reply_to as ub_id, COUNT(*) as replies',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_in_reply_to IN ('.implode(',',array_keys($commentIds)).')',
			'group'  => 'b.ub_in_reply_to',
		]);
		while ($row = $mouse->DB->fetch($repliesRes)) {
			$comments[$commentIds[$row['ub_id']]]['reply_count'] = intval($row['replies']);
			if ($row['replies'] > 0) {
				$comments[$commentIds[$row['ub_id']]]['replies'] = $this->getReplies($row['ub_id'], $asUser);
			}
		}

		return $comments;
	}

	/**
	 * Gets all replies to a given comment
	 *
	 * @param	int		id of a comment that would be replied to
	 * @param	int		[optional] user ID of user viewing (defaults to wgUser)
	 * @param	int		[optional] max number items to return (less recent replies will be ommitted)
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
	 * @param	int		the ID of a user
	 * @param	int		optional user ID of user posting (defaults to wgUser)
	 * @param	int		optional id of a board post that this will be in reply to
	 */
	public function addComment($commentText, $fromUser = null, $inReplyTo = null) {
		$commentText = trim($commentText);
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

		if ($toUser->getID() != $fromUser->getID() && $toUser->getEmail() && $toUser->getIntOption('commentemail')) {
			if (trim($toUser->getRealName())) {
				$name = $toUser->getRealName();
			} else {
				$name = $toUser->getName();
			}
			$updatePrefsLink = \SpecialPage::getTitleFor('Preferences');
			$subject = wfMessage('commentemail-subj', $fromUser->getName())->text();
			$body = wfMessage('commentemail-body')->params(
					$name,
					$fromUser->getName(),
					$toUser->getUserPage()->getFullURL(),
					$updatePrefsLink->getFullURL().'#mw-prefsection-personal-email'
				)->text();
			$toUser->sendMail($subject, $body);
		}
	}
}

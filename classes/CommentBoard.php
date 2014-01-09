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
	 * Gets the comments on the board
	 *
	 * @param	int		optional user ID of user viewing (defaults to wgUser)
	 * @return	array	an array of comment data (text and user info)
	 */
	public function getComments($asUser = null) {
		$types = [self::PUBLIC_MESSAGE];
		if (is_null($asUser)) {
			global $wgUser;
			$asUser = $wgUser->getID();
		}
		if ($this->user_id == $asUser) {
			$types[] = self::PRIVATE_MESSAGE;
		}
		$mouse = CP::loadMouse();
		$res = $mouse->DB->select([
			'select' => 'b.*',
			'from'   => ['user_board' => 'b'],
			'where'  => 'b.ub_type IN ('.implode(',',$types).') AND b.ub_user_id = '.$this->user_id,
			'order'  => 'b.ub_date DESC',
		]);
		$comments = [];
		while ($row = $mouse->DB->fetch($res)){
			$comments[] = $row;
		}
		return $comments;
	}

	/**
	 * Add a public comment to the board
	 *
	 * @param	int		the ID of a user
	 * @param	int		optional user ID of user posting (defaults to wgUser)
	 */
	public function addComment($commentText, $fromUser = null) {
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

		$dbw->insert(
			'user_board',
			array(
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
	}
}

<?php
/**
 * Curse Profile
 *
 * @package   CurseProfile
 * @author    Alexia E. Smith
 * @copyright (c) 2020 Fandom
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use Hooks;
use MWException;
use MWTimestamp;
use Sanitizer;
use User;

/**
 * Handles a single comment.
 */
class Comment {
	/**
	 * Comment Data
	 *
	 * @var array
	 */
	private $data = [
		'ub_id' => 0,
		'ub_in_reply_to' => 0,
		'ub_user_id' => 0,
		'ub_user_name' => '',
		'ub_user_id_from' => 0,
		'ub_user_name_from' => 0,
		'ub_message' => '',
		'ub_type' => 0,
		'ub_date' => null,
		'ub_last_reply' => null,
		'ub_edited' => null,
		'ub_admin_acted_user_id' => null,
		'ub_admin_acted_at' => null
	];

	// Maximum character length of a single comment.
	const MAX_LENGTH = 5000;

	/**
	 * Message visibility constants
	 */
	const DELETED_MESSAGE = -1;
	const PUBLIC_MESSAGE = 0;
	const PRIVATE_MESSAGE = 1;

	/**
	 * Setup and validate data for this class.
	 *
	 * @param array [Optional] $comment Database row for a comment.
	 *
	 * @throws MWException
	 */
	public function __construct(array $comment = []) {
		if (count(array_diff_key($comment, $this->data))) {
			throw new MWException(__METHOD__ . " Comment data contained invalid keys.");
		}
		$this->data = array_merge($this->data, $comment);
	}

	/**
	 * Get a new Comment instance based on a comment ID(ub_id).
	 *
	 * @param integer $commentId The comment ID.
	 *
	 * @return Comment|null
	 */
	public static function newFromId(int $commentId): ?self {
		if ($commentId < 1) {
			return null;
		}

		// Look up the target comment.
		$comment = self::queryCommentById($commentId);

		if (empty($comment)) {
			return null;
		}

		return new self($comment);
	}

	/**
	 * Get a new unsaved comment with an attached board owner.
	 *
	 * @param User $owner The board owner.
	 *
	 * @return Comment
	 */
	public static function newWithOwner(User $owner): self {
		$comment = new self();
		$comment->setBoardOwnerUser($owner);
		return $comment;
	}

	/**
	 * Get a raw comment from the database by ID.
	 *
	 * @param integer $commentId Comment ID
	 *
	 * @return mixed	Database result or false.
	 */
	private static function queryCommentById(int $commentId) {
		$db = wfGetDB(DB_REPLICA);
		$result = $db->select(
			['user_board'],
			['*'],
			['ub_id' => $commentId],
			__METHOD__
		);

		return $result->fetchRow();
	}

	/**
	 * Save changes to the database.
	 *
	 * @return boolean Save Success
	 */
	public function save(): bool {
		$db = wfGetDB(DB_MASTER);
		$success = false;

		$db->startAtomic(__METHOD__);
		$data = $this->data;
		if ($this->data['ub_id'] > 0) {
			unset($data['ub_id']);

			$result = $db->update(
				'user_board',
				$data,
				['ub_id' => $this->data['ub_id']],
				__METHOD__
			);
		} else {
			$result = $db->insert(
				'user_board',
				$data,
				__METHOD__
			);
		}
		if (!$result) {
			$db->cancelAtomic(__METHOD__);
		} else {
			$success = true;
			$this->data['ub_id'] = $db->insertId();
		}

		if ($success) {
			if ($this->getParentCommentId() > 0) {
				$db->update(
					'user_board',
					[
						'ub_last_reply' => $this->convertUnixDateToDB(time())
					],
					['ub_id = ' . $this->getParentCommentId()],
					__METHOD__
				);
			}
		}
		$db->endAtomic(__METHOD__);

		return $success;
	}

	/**
	 * Gets all comment replies to this comment.
	 *
	 * @param User    $actor The user viewing these comments to limit visibility and give an accurate count.
	 * @param integer $limit [Optional] Maximum number items to return (older replies will be ommitted)
	 *
	 * @return array Array of Comment instances.
	 */
	public function getReplies(User $actor = null, int $limit = 5) {
		// Fetch comments.
		$options = [
			'ORDER BY'	=> 'ub_date DESC'
		];

		if ($limit > 0) {
			$options['LIMIT'] = intval($limit);
		}

		$db = wfGetDB(DB_REPLICA);
		$results = $db->select(
			['user_board'],
			[
				'*',
			],
			[
				CommentBoard::visibleClause($actor),
				'ub_in_reply_to' => $this->getId(),
				'ub_user_id' => $this->getBoardOwnerUserId()
			],
			__METHOD__,
			$options
		);

		$comments = [];
		while ($row = $results->fetchRow()) {
			$comments[] = new Comment($row);
		}

		return array_reverse($comments);
	}

	/**
	 * Return the number of replies to this comment.
	 *
	 * @param User $actor The user viewing these comments to limit visibility and give an accurate count.
	 *
	 * @return integer Total number of replies to this comment.
	 */
	public function getTotalReplies(User $actor): int {
		$db = wfGetDB(DB_REPLICA);
		$result = $db->select(
			['user_board'],
			[
				'count(ub_in_reply_to) as total_replies',
			],
			[
				CommentBoard::visibleClause($actor),
				'ub_in_reply_to' => $this->getId(),
				'ub_user_id' => $this->getBoardOwnerUserId()
			],
			__METHOD__,
			[
				'GROUP BY' => 'ub_in_reply_to'
			]
		);
		$row = $result->fetchRow();

		return intval($row['total_replies']);
	}

	/**
	 * Checks if a user should be able to view a specific comment
	 *
	 * @param User $user User object
	 *
	 * @return boolean
	 */
	public function canView(User $user) {
		// Early check for admin status.
		if ($user->isAllowed('profile-moderate')) {
			return true;
		}

		// PUBLIC comments visible to all, DELETED comments visible to the author, PRIVATE to author and recipient.
		return $this->getType() === self::PUBLIC_MESSAGE
			|| ($this->getType() === self::PRIVATE_MESSAGE && $this->getBoardOwnerUser() === $user->getId() && $this->getActorUserId() === $user->getId())
			|| ($this->getType() === self::DELETED_MESSAGE && $this->getActorUserId() == $user->getId());
	}

	/**
	 * Checks if a user has permissions to leave a comment.
	 *
	 * @param User $fromUser User object for comment author, defaults to $wgUser.
	 *
	 * @return boolean Can Comment
	 */
	public function canComment(User $fromUser) {
		global $wgCPEditsToComment, $wgEmailAuthentication, $wgUser;

		$toUser = $this->getBoardOwnerUser();
		if ($toUser->isAnon()) {
			return false;
		}

		$editCount = $fromUser->getEditCount();

		$noEmailAuth = ($wgEmailAuthentication && (!boolval($fromUser->getEmailAuthenticationTimestamp()) || !Sanitizer::validateEmail($fromUser->getEmail())));

		if ($fromUser->getId()) {
			if (!Hooks::run('CurseProfileCanComment', [$fromUser, $toUser, $wgCPEditsToComment])) {
				return false;
			}

		}

		// User must be logged in, must not be blocked, and target must not be blocked (with exception for admins).
		return !$noEmailAuth && $fromUser->isLoggedIn() && !$fromUser->isBlocked() && (!$toUser->isBlocked() || $fromUser->isAllowed('block'));
	}

	/**
	 * Checks if a user has permissions to reply to a comment
	 *
	 * @param User $actor User that wishes to reply.
	 *
	 * @return boolean
	 */
	public function canReply(User $actor) {
		// comment must not be deleted and user must be logged in
		return $this->getType() > self::DELETED_MESSAGE && $this->canComment($actor);
	}

	/**
	 * Checks if a user has permissions to edit a comment
	 *
	 * @param User $actor User performing this action.
	 *
	 * @return boolean
	 */
	public function canEdit(User $actor) {
		// comment must not be deleted and must be written by this user
		return $this->getType() > self::DELETED_MESSAGE && $this->getActorUserId() === $actor->getId();
	}

	/**
	 * Checks if a user has permissions to remove a comment
	 *
	 * @param User $actor User performing the action.
	 *
	 * @return boolean
	 */
	public function canRemove(User $actor) {
		// user must not be blocked, comment must either be authored by current user or on user's profile
		return $this->getType() !== self::DELETED_MESSAGE && !$actor->isBlocked() &&
			($this->getBoardOwnerUserId() === $actor->getId()
				|| ($this->getActorUserId() === $actor->getId())
				|| $actor->isAllowed('profile-moderate'));
	}

	/**
	 * Checks if a user has permissions to restore a deleted comment.
	 *
	 * @param User $actor User performing the action.
	 *
	 * @return boolean
	 */
	public function canRestore(User $actor) {
		// comment must be deleted, user has mod permissions or was the original author and deleter
		return $this->getType() === self::DELETED_MESSAGE &&
			(
				$actor->isAllowed('profile-moderate')
				|| $this->getBoardOwnerUserId() === $actor->getId()
				&& $this->getAdminActedUserId() === $actor->getId()
			);
	}

	/**
	 * Checks if a user has permissions to permanently remove a comment.
	 *
	 * @param User $actor User performing this action.
	 *
	 * @return boolean
	 */
	public function canPurge(User $actor) {
		return $actor->isAllowed('profile-purgecomments');
	}

	/**
	 * Checks if a user has permissions to report a comment
	 *
	 * @param User $actor User performing this action.
	 *
	 * @return boolean
	 */
	public function canReport(User $actor) {
		// user must be logged-in to report and comment must be public (not deleted)
		return !$actor->isAnon() && $this->getActorUserId() !== $actor->getId() && $this->getType() === self::PUBLIC_MESSAGE;
	}

	/**
	 * Return the comment database ID.
	 *
	 * @return integer
	 */
	public function getId(): int {
		return intval($this->data['ub_id']);
	}

	/**
	 * Return the comment content.
	 *
	 * @return string
	 */
	public function getMessage(): string {
		return $this->data['ub_message'];
	}

	/**
	 * Set the comment content.
	 *
	 * @param string $message Comment
	 *
	 * @return null
	 */
	public function setMessage(string $message) {
		$this->data['ub_message'] = trim(substr($message, 0, self::MAX_LENGTH));
	}

	/**
	 * Return the comment type.(Public, Archived, Deleted)
	 *
	 * @return integer
	 */
	public function getType(): int {
		return intval($this->data['ub_type']);
	}

	/**
	 * Mark this message as public.
	 *
	 * @return null
	 */
	public function markAsPublic() {
		$this->data['ub_type'] = self::PUBLIC_MESSAGE;
	}

	/**
	 * Mark this message as deleted.
	 *
	 * @return null
	 */
	public function markAsDeleted() {
		$this->data['ub_type'] = self::DELETED_MESSAGE;
	}

	/**
	 * Mark this message as private.
	 *
	 * @return null
	 */
	public function markAsPrivate() {
		$this->data['ub_type'] = self::PRIVATE_MESSAGE;
	}

	/**
	 * Get the User instance of the user that made the comment.
	 *
	 * @return User
	 */
	public function getActorUser(): User {
		return User::newFromId($this->getActorUserId());
	}

	/**
	 * Set the user ID and user name from an User instance of the user that made the comment.
	 *
	 * @param integer User ID
	 *
	 * @return null
	 */
	public function setActorUser(User $user) {
		$this->data['ub_user_id_from'] = $user->getId();
		$this->data['ub_user_name_from'] = $user->getName();
	}

	/**
	 * Get the user ID of the user that made the comment.
	 *
	 * @return integer User ID
	 */
	public function getActorUserId(): int {
		return intval($this->data['ub_user_id_from']);
	}

	/**
	 * Set the user ID of the user that made the comment.
	 *
	 * @param integer User ID
	 *
	 * @return null
	 */
	public function setActorUserId(int $userId) {
		$this->data['ub_user_id_from'] = $userId;
	}

	/**
	 * Get the User instance of the user board that this comment belongs to.
	 *
	 * @return User
	 */
	public function getBoardOwnerUser(): User {
		return User::newFromId($this->getBoardOwnerUserId());
	}

	/**
	 * Set the user ID from an User instance of the user board that this comment belongs to.
	 *
	 * @param integer User ID
	 *
	 * @return null
	 */
	public function setBoardOwnerUser(User $user) {
		$this->data['ub_user_id'] = $user->getId();
		$this->data['ub_user_name'] = $user->getName();
	}

	/**
	 * Get the user ID of the user board that this comment belongs to.
	 *
	 * @return integer Board Owner User ID
	 */
	public function getBoardOwnerUserId(): int {
		return intval($this->data['ub_user_id']);
	}

	/**
	 * Set the user ID of the user board that this comment belongs to.
	 *
	 * @param integer User ID
	 *
	 * @return null
	 */
	public function setBoardOwnerUserId(int $userId) {
		$this->data['ub_user_id'] = $userId;
	}

	/**
	 * Get the User instance of the the administrator that performed an administrative action on this comment.
	 *
	 * @return User
	 */
	public function getAdminActedUser(): User {
		return User::newFromId($this->getAdminActedUserId());
	}

	/**
	 * Set the user ID from an User instance of the the administrator that performed an administrative action on this comment.
	 *
	 * @param integer User ID
	 *
	 * @return null
	 */
	public function setAdminActedUser(User $user) {
		$this->data['ub_admin_acted_user_id'] = $user->getId();
	}

	/**
	 * Get the user ID of the the administrator that performed an administrative action on this comment.
	 *
	 * @return integer Admin Acted User ID
	 */
	public function getAdminActedUserId(): int {
		return intval($this->data['ub_admin_acted_user_id']);
	}

	/**
	 * Set the user ID of the the administrator that performed an administrative action on this comment.
	 *
	 * @param integer User ID
	 *
	 * @return null
	 */
	public function setAdminActedUserId(?int $userId = null) {
		$this->data['ub_admin_acted_user_id'] = $userId;
	}

	/**
	 * Get the post(creation) timestamp.
	 *
	 * @return integer|null
	 */
	public function getPostTimestamp(): ?int {
		return $this->convertDBDateToUnix($this->data['ub_date']);
	}

	/**
	 * Set the post(creation) timestamp.
	 *
	 * @param integer|null
	 *
	 * @return null
	 */
	public function setPostTimestamp(?int $timestamp = null) {
		$this->data['ub_date'] = $this->convertUnixDateToDB($timestamp);
	}

	/**
	 * Get the edit timestamp.
	 *
	 * @return integer|null
	 */
	public function getEditTimestamp(): ?int {
		return $this->convertDBDateToUnix($this->data['ub_edited']);
	}

	/**
	 * Set the edit timestamp.
	 *
	 * @param integer|null
	 *
	 * @return null
	 */
	public function setEditTimestamp(?int $timestamp = null) {
		$this->data['ub_edited'] = $this->convertUnixDateToDB($timestamp);
	}

	/**
	 * Get the last reply timestamp.
	 *
	 * @return integer|null
	 */
	public function getLastReplyTimestamp(): ?int {
		return $this->convertDBDateToUnix($this->data['ub_last_reply']);
	}

	/**
	 * Set the last reply timestamp.
	 *
	 * @param integer|null
	 *
	 * @return null
	 */
	public function setLastReplyTimestamp(?int $timestamp = null) {
		$this->data['ub_last_reply'] = $this->convertUnixDateToDB($timestamp);
	}

	/**
	 * Get the timestamp for an administrator performed an action on this comment.
	 *
	 * @return integer|null
	 */
	public function getAdminActionTimestamp(): ?int {
		return $this->convertDBDateToUnix($this->data['ub_admin_acted_at']);
	}

	/**
	 * Set the timestamp for an administrator performed an action on this comment.
	 *
	 * @param integer|null
	 *
	 * @return null
	 */
	public function setAdminActionTimestamp(?int $timestamp = null) {
		$this->data['ub_admin_acted_at'] = $this->convertUnixDateToDB($timestamp);
	}

	/**
	 * Process a database date string or null into an Unix date.
	 *
	 * @param string $date Database Date String
	 *
	 * @return integer|null
	 */
	private function convertDBDateToUnix(?string $date): ?int {
		if (empty($date)) {
			return null;
		}

		$timestamp = new MWTimestamp($date);
		return $timestamp->getTimestamp(TS_UNIX);
	}

	/**
	 * Process an Unix date string or null into a database date.
	 *
	 * @param string $date Unix Date String
	 *
	 * @return string|null
	 */
	private function convertUnixDateToDB(?string $date): ?string {
		if (empty($date)) {
			return null;
		}

		$timestamp = new MWTimestamp($date);
		return $timestamp->getTimestamp(TS_DB);
	}

	/**
	 * Get the comment ID of the parent comment to this one.
	 *
	 * @return integer Parent Comment ID
	 */
	public function getParentCommentId(): int {
		return intval($this->data['ub_in_reply_to']);
	}

	/**
	 * Set the comment ID of the parent comment to this one.
	 *
	 * @param integer [Optional] Parent Comment ID
	 *
	 * @return null
	 */
	public function setParentCommentId(int $parentId = 0) {
		$this->data['ub_in_reply_to'] = $parentId;
	}
}

<?php
/**
 * Curse Profile
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
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
	 * @param array $comment Database row for a comment.
	 *
	 * @throws MWException
	 */
	public function __construct(array $comment) {
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
	 * @return self
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
	 * @return self
	 */
	public static function newWithOwner(User $owner): ?self {
		return new self(['ub_user_id' => $owner->getId()]);
	}

	/**
	 * Get a raw comment from the database by ID.
	 *
	 * @param integer $commentId Comment ID
	 *
	 * @return mixed	Database result or false.
	 */
	private static function queryCommentById(int $commentId) {
		$DB = wfGetDB(DB_REPLICA);
		$result = $DB->select(
			['user_board'],
			['*'],
			['ub_id' => $commentId],
			__METHOD__
		);

		return $result->fetchRow();
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
			|| ($this->getType() === self::PRIVATE_MESSAGE && $this->getBoardOwnerUser() === $user->getId() && $this->$this->getActorUserId() === $user->getId())
			|| ($this->getType() === self::DELETED_MESSAGE && $this->$this->getActorUserId() == $user->getId());
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
		return !$noEmailAuth && $fromUser->isLoggedIn() && !$fromUser->isBlocked() && ((!$toUser->isBlocked()) || $fromUser->isAllowed('block'));
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
	 * Return the comment type.(Public, Archived, Deleted)
	 *
	 * @return integer
	 */
	public function getType(): int {
		return intval($this->data['ub_type']);
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
	 * Get the user ID of the user that made the comment.
	 *
	 * @return integer User ID
	 */
	public function getActorUserId(): int {
		return intval($this->data['ub_user_id_from']);
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
	 * Get the user ID of the user board that this comment belongs to.
	 *
	 * @return integer Board Owner User ID
	 */
	public function getBoardOwnerUserId(): int {
		return intval($this->data['ub_user_id']);
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
	 * Get the user ID of the the administrator that performed an administrative action on this comment.
	 *
	 * @return integer Admin Acted User ID
	 */
	public function getAdminActedUserId(): int {
		return intval($this->data['ub_admin_acted_user_id']);
	}

	/**
	 * Get the post(creation) timestamp.
	 *
	 * @return integer|null
	 */
	public function getPostTimestamp(): ?int {
		return $this->processDate($this->data['ub_date']);
	}

	/**
	 * Get the edit timestamp.
	 *
	 * @return integer|null
	 */
	public function getEditTimestamp(): ?int {
		return $this->processDate($this->data['ub_edited']);
	}

	/**
	 * Get the last reply timestamp.
	 *
	 * @return integer|null
	 */
	public function getLastReplyTimestamp(): ?int {
		return $this->processDate($this->data['ub_last_reply']);
	}

	/**
	 * Get the timestamp for an administrator performed an action on this comment.
	 *
	 * @return integer|null
	 */
	public function getAdminActionTimestamp(): ?int {
		return $this->processDate($this->data['ub_admin_acted_at']);
	}

	/**
	 * Process a database date string or null into a usable response.
	 *
	 * @param string $date Database Date String
	 *
	 * @return integer|null
	 */
	private function processDate(?string $date): ?int {
		if (empty($date)) {
			return null;
		}

		$timestamp = new MWTimestamp($date);
		return $timestamp->getTimestamp(TS_UNIX);
	}
}

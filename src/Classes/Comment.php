<?php
/**
 * Curse Profile
 *
 * @package   CurseProfile
 * @author    Alexia E. Smith
 * @copyright (c) 2020 Fandom
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Classes;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
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
	public const MAX_LENGTH = 5000;

	/**
	 * Message visibility constants
	 */
	public const DELETED_MESSAGE = -1;
	public const PUBLIC_MESSAGE = 0;
	public const PRIVATE_MESSAGE = 1;

	/**
	 * Setup and validate data for this class.
	 *
	 * @param array [Optional] $comment Database row for a comment.
	 *
	 * @throws MWException
	 */
	public function __construct( array $comment = [] ) {
		$comment = array_intersect_key( $comment, $this->data );
		if ( count( array_diff_key( $comment, $this->data ) ) ) {
			throw new MWException( __METHOD__ . " Comment data contained invalid keys." );
		}
		$this->data = array_merge( $this->data, $comment );
	}

	/**
	 * Get a new Comment instance based on a comment ID(ub_id).
	 *
	 * @param int $commentId The comment ID.
	 *
	 * @return Comment|null
	 */
	public static function newFromId( int $commentId ): ?self {
		if ( $commentId < 1 ) {
			return null;
		}

		// Look up the target comment.
		$comment = self::queryCommentById( $commentId );

		if ( empty( $comment ) ) {
			return null;
		}

		return new self( $comment );
	}

	/**
	 * Get a new unsaved comment with an attached board owner.
	 */
	public static function newWithOwner( UserIdentity $owner ): self {
		$comment = new self();
		$comment->setBoardOwnerUser( $owner );
		return $comment;
	}

	/**
	 * Get a raw comment from the database by ID.
	 *
	 * @param int $commentId Comment ID
	 *
	 * @return mixed Database result or false.
	 */
	private static function queryCommentById( int $commentId ) {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $db->select(
			[ 'user_board' ],
			[ '*' ],
			[ 'ub_id' => $commentId ],
			__METHOD__
		);

		return $result->fetchRow();
	}

	/**
	 * Save changes to the database.
	 *
	 * @return bool Save Success
	 */
	public function save(): bool {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$success = false;

		$db->startAtomic( __METHOD__ );
		$data = $this->data;
		if ( $this->data['ub_id'] > 0 ) {
			unset( $data['ub_id'] );

			$result = $db->update(
				'user_board',
				$data,
				[ 'ub_id' => $this->data['ub_id'] ],
				__METHOD__
			);
		} else {
			$result = $db->insert(
				'user_board',
				$data,
				__METHOD__
			);
		}
		if ( !$result ) {
			$db->cancelAtomic( __METHOD__ );
		} else {
			$success = true;
			$this->data['ub_id'] = $db->insertId();
		}

		if ( $success ) {
			if ( $this->getParentCommentId() > 0 ) {
				$db->update(
					'user_board',
					[
						'ub_last_reply' => $this->convertUnixDateToDB( time() )
					],
					[ 'ub_id = ' . $this->getParentCommentId() ],
					__METHOD__
				);
			}
		}
		$db->endAtomic( __METHOD__ );

		return $success;
	}

	/**
	 * Gets all comment replies to this comment.
	 *
	 * @param User|null $actor The user viewing these comments to limit visibility and give an accurate count.
	 * @param int $limit [Optional] Maximum number items to return (older replies will be ommitted)
	 *
	 * @return array Array of Comment instances.
	 */
	public function getReplies( User $actor = null, int $limit = 5 ) {
		// Fetch comments.
		$options = [
			'ORDER BY'	=> 'ub_date DESC'
		];

		if ( $limit > 0 ) {
			$options['LIMIT'] = $limit;
		}

		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$results = $db->select(
			[ 'user_board' ],
			[
				'*',
			],
			[
				CommentBoard::visibleClause( $actor ),
				'ub_in_reply_to' => $this->getId(),
				'ub_user_id' => $this->getBoardOwnerUserId()
			],
			__METHOD__,
			$options
		);

		$comments = [];
		while ( $row = $results->fetchRow() ) {
			$comments[] = new Comment( $row );
		}

		return array_reverse( $comments );
	}

	/**
	 * Return the number of replies to this comment.
	 *
	 * @param User $actor The user viewing these comments to limit visibility and give an accurate count.
	 *
	 * @return int Total number of replies to this comment.
	 */
	public function getTotalReplies( User $actor ): int {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $db->select(
			[ 'user_board' ],
			[
				'count(ub_in_reply_to) as total_replies',
			],
			[
				CommentBoard::visibleClause( $actor ),
				'ub_in_reply_to' => $this->getId(),
				'ub_user_id' => $this->getBoardOwnerUserId()
			],
			__METHOD__,
			[
				'GROUP BY' => 'ub_in_reply_to'
			]
		);
		$row = $result->fetchRow();

		return (int)$row['total_replies'];
	}

	/**
	 * Checks if a user should be able to view a specific comment
	 *
	 * @param User $user User object
	 *
	 * @return bool
	 */
	public function canView( User $user ) {
		// Early check for admin status.
		if ( $user->isAllowed( 'profile-comments-moderate' ) ) {
			return true;
		}

		// PUBLIC comments visible to all, DELETED comments visible to the author, PRIVATE to author and recipient.
		return $this->getType() === self::PUBLIC_MESSAGE
			|| ( $this->getType() === self::PRIVATE_MESSAGE &&
				$this->getBoardOwnerUser() === $user->getId() &&
				$this->getActorUserId() === $user->getId() )
			|| ( $this->getType() === self::DELETED_MESSAGE && $this->getActorUserId() == $user->getId() );
	}

	/**
	 * Checks if a user has permissions to leave a comment.
	 *
	 * @param User $fromUser User object for comment author, defaults to $wgUser.
	 *
	 * @return bool Can Comment
	 */
	public function canComment( User $fromUser ) {
		global $wgCPEditsToComment, $wgEmailAuthentication, $wgUser;

		$toUser = $this->getBoardOwnerUser();
		if ( $toUser->isAnon() ) {
			return false;
		}

		$editCount = $fromUser->getEditCount();

		$noEmailAuth = ( $wgEmailAuthentication &&
			( !$fromUser->getEmailAuthenticationTimestamp() ||
				!Sanitizer::validateEmail( $fromUser->getEmail() ) ) );

		if ( $fromUser->getId() ) {
			if ( $fromUser->getId() == $toUser->getId() ) {
				return !$fromUser->getBlock();
			}
			$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
			if ( !$hookContainer->run( 'CurseProfileCanComment', [ $fromUser, $toUser, $wgCPEditsToComment ] ) ) {
				return false;
			}
		}

		// User must be logged in, must not be blocked, and target must not be blocked (with exception for admins).
		return !$noEmailAuth &&
			$fromUser->isRegistered() &&
			!$fromUser->getBlock() &&
			( !$toUser->getBlock() || $fromUser->isAllowed( 'block' ) );
	}

	/**
	 * Checks if a user has permissions to reply to a comment
	 *
	 * @param User $actor User that wishes to reply.
	 *
	 * @return bool
	 */
	public function canReply( User $actor ) {
		// comment must not be deleted and user must be logged in
		return $this->getType() > self::DELETED_MESSAGE && $this->canComment( $actor );
	}

	/**
	 * Checks if a user has permissions to edit a comment
	 *
	 * @param User $actor User performing this action.
	 *
	 * @return bool
	 */
	public function canEdit( User $actor ) {
		// comment must not be deleted and must be written by this user
		return $this->getType() > self::DELETED_MESSAGE && $this->getActorUserId() === $actor->getId();
	}

	/**
	 * Checks if a user has permissions to remove a comment
	 *
	 * @param User $actor User performing the action.
	 *
	 * @return bool
	 */
	public function canRemove( User $actor ) {
		// user must not be blocked, comment must either be authored by current user or on user's profile
		return $this->getType() !== self::DELETED_MESSAGE && !$actor->getBlock() &&
			( $this->getBoardOwnerUserId() === $actor->getId()
				|| ( $this->getActorUserId() === $actor->getId() )
				|| $actor->isAllowed( 'profile-comments-moderate' ) );
	}

	/**
	 * Checks if a user has permissions to restore a deleted comment.
	 *
	 * @param User $actor User performing the action.
	 *
	 * @return bool
	 */
	public function canRestore( User $actor ) {
		// comment must be deleted, user has mod permissions or was the original author and deleter
		return $this->getType() === self::DELETED_MESSAGE &&
			(
				$actor->isAllowed( 'profile-comments-moderate' )
				|| ( $this->getBoardOwnerUserId() === $actor->getId()
					&& $this->getAdminActedUserId() === $actor->getId() )
			);
	}

	/**
	 * Checks if a user has permissions to permanently remove a comment.
	 *
	 * @param User $actor User performing this action.
	 *
	 * @return bool
	 */
	public function canPurge( User $actor ) {
		return $actor->isAllowed( 'profile-purgecomments' );
	}

	/**
	 * Checks if a user has permissions to report a comment
	 *
	 * @param User $actor User performing this action.
	 *
	 * @return bool
	 */
	public function canReport( User $actor ) {
		// user must be logged-in to report and comment must be public (not deleted)
		return !$actor->isAnon() &&
			$this->getActorUserId() !== $actor->getId() &&
			$this->getType() === self::PUBLIC_MESSAGE;
	}

	/**
	 * Return the comment database ID.
	 *
	 * @return int
	 */
	public function getId(): int {
		return (int)$this->data['ub_id'];
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
	 */
	public function setMessage( string $message ) {
		$this->data['ub_message'] = trim( substr( $message, 0, self::MAX_LENGTH ) );
	}

	/**
	 * Return the comment type.(Public, Archived, Deleted)
	 *
	 * @return int
	 */
	public function getType(): int {
		return (int)$this->data['ub_type'];
	}

	/**
	 * Mark this message as public.
	 */
	public function markAsPublic(): void {
		$this->data['ub_type'] = self::PUBLIC_MESSAGE;
	}

	/**
	 * Mark this message as deleted.
	 */
	public function markAsDeleted(): void {
		$this->data['ub_type'] = self::DELETED_MESSAGE;
	}

	/**
	 * Mark this message as private.
	 */
	public function markAsPrivate(): void {
		$this->data['ub_type'] = self::PRIVATE_MESSAGE;
	}

	/**
	 * Get the User instance of the user that made the comment.
	 *
	 * @return User
	 */
	public function getActorUser(): User {
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->getActorUserId() );
	}

	/**
	 * Set the user ID and user name from an User instance of the user that made the comment.
	 */
	public function setActorUser( User $user ): void {
		$this->data['ub_user_id_from'] = $user->getId();
		$this->data['ub_user_name_from'] = $user->getName();
	}

	/**
	 * Get the user ID of the user that made the comment.
	 *
	 * @return int User ID
	 */
	public function getActorUserId(): int {
		return (int)$this->data['ub_user_id_from'];
	}

	/**
	 * Set the user ID of the user that made the comment.
	 */
	public function setActorUserId( int $userId ): void {
		$this->data['ub_user_id_from'] = $userId;
	}

	/**
	 * Get the User instance of the user board that this comment belongs to.
	 *
	 * @return User
	 */
	public function getBoardOwnerUser(): User {
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->getBoardOwnerUserId() );
	}

	/**
	 * Set the user ID from an User instance of the user board that this comment belongs to.
	 */
	public function setBoardOwnerUser( UserIdentity $user ): void {
		$this->data['ub_user_id'] = $user->getId();
		$this->data['ub_user_name'] = $user->getName();
	}

	/**
	 * Get the user ID of the user board that this comment belongs to.
	 *
	 * @return int Board Owner User ID
	 */
	public function getBoardOwnerUserId(): int {
		return (int)$this->data['ub_user_id'];
	}

	/**
	 * Set the user ID of the user board that this comment belongs to.
	 *
	 * @param int $userId User ID
	 */
	public function setBoardOwnerUserId( int $userId ): void {
		$this->data['ub_user_id'] = $userId;
	}

	/**
	 * Get the User instance of the the administrator that performed an administrative action on this comment.
	 *
	 * @return User
	 */
	public function getAdminActedUser(): User {
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->getAdminActedUserId() );
	}

	/**
	 * Set the user ID from an User instance of the the administrator
	 * that performed an administrative action on this comment.
	 *
	 * @param int $user User ID
	 */
	public function setAdminActedUser( User $user ): void {
		$this->data['ub_admin_acted_user_id'] = $user->getId();
	}

	/**
	 * Get the user ID of the the administrator that performed an administrative action on this comment.
	 *
	 * @return int Admin Acted User ID
	 */
	public function getAdminActedUserId(): int {
		return (int)$this->data['ub_admin_acted_user_id'];
	}

	/**
	 * Set the user ID of the the administrator that performed an administrative action on this comment.
	 */
	public function setAdminActedUserId( ?int $userId = null ): void {
		$this->data['ub_admin_acted_user_id'] = $userId;
	}

	/**
	 * Get the post(creation) timestamp.
	 *
	 * @return int|null
	 */
	public function getPostTimestamp(): ?int {
		return $this->convertDBDateToUnix( $this->data['ub_date'] );
	}

	/**
	 * Set the post(creation) timestamp.
	 *
	 * @param int|null $timestamp
	 */
	public function setPostTimestamp( ?int $timestamp = null ): void {
		$this->data['ub_date'] = $this->convertUnixDateToDB( $timestamp );
	}

	/**
	 * Get the edit timestamp.
	 *
	 * @return int|null
	 */
	public function getEditTimestamp(): ?int {
		return $this->convertDBDateToUnix( $this->data['ub_edited'] );
	}

	/**
	 * Set the edit timestamp.
	 *
	 * @param int|null $timestamp
	 */
	public function setEditTimestamp( ?int $timestamp = null ): void {
		$this->data['ub_edited'] = $this->convertUnixDateToDB( $timestamp );
	}

	/**
	 * Get the last reply timestamp.
	 *
	 * @return int|null
	 */
	public function getLastReplyTimestamp(): ?int {
		return $this->convertDBDateToUnix( $this->data['ub_last_reply'] );
	}

	/**
	 * Set the last reply timestamp.
	 *
	 * @param int|null $timestamp
	 */
	public function setLastReplyTimestamp( ?int $timestamp = null ): void {
		$this->data['ub_last_reply'] = $this->convertUnixDateToDB( $timestamp );
	}

	/**
	 * Get the timestamp for an administrator performed an action on this comment.
	 *
	 * @return int|null
	 */
	public function getAdminActionTimestamp(): ?int {
		return $this->convertDBDateToUnix( $this->data['ub_admin_acted_at'] );
	}

	/**
	 * Set the timestamp for an administrator performed an action on this comment.
	 *
	 * @param int|null $timestamp
	 */
	public function setAdminActionTimestamp( ?int $timestamp = null ): void {
		$this->data['ub_admin_acted_at'] = $this->convertUnixDateToDB( $timestamp );
	}

	/**
	 * Process a database date string or null into an Unix date.
	 *
	 * @param string $date Database Date String
	 *
	 * @return int|null
	 */
	private function convertDBDateToUnix( ?string $date ): ?int {
		if ( empty( $date ) ) {
			return null;
		}

		$timestamp = new MWTimestamp( $date );
		return $timestamp->getTimestamp( TS_UNIX );
	}

	/**
	 * Process an Unix date string or null into a database date.
	 *
	 * @param string $date Unix Date String
	 *
	 * @return string|null
	 */
	private function convertUnixDateToDB( ?string $date ): ?string {
		if ( empty( $date ) ) {
			return null;
		}

		$timestamp = new MWTimestamp( $date );
		return $timestamp->getTimestamp( TS_DB );
	}

	/**
	 * Get the comment ID of the parent comment to this one.
	 *
	 * @return int Parent Comment ID
	 */
	public function getParentCommentId(): int {
		return (int)$this->data['ub_in_reply_to'];
	}

	/**
	 * Set the comment ID of the parent comment to this one.
	 *
	 * @param int $parentId [Optional] Parent Comment ID
	 */
	public function setParentCommentId( int $parentId = 0 ): void {
		$this->data['ub_in_reply_to'] = $parentId;
	}
}

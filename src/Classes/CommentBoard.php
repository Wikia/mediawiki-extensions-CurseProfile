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
 */

namespace CurseProfile\Classes;

use Exception;
use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use Reverb\Notification\NotificationBroadcastFactory;
use SpecialPage;
use Title;
use User;

/**
 * Class that manages a 'wall' of comments on a user profile page
 */
class CommentBoard {
	/**
	 * Board type constants
	 */
	// recent comments shown on a person's profile
	public const BOARDTYPE_RECENT = 1;
	// archive page that shows all comments
	public const BOARDTYPE_ARCHIVES = 2;

	public function __construct( private User $owner, private int $type = self::BOARDTYPE_RECENT ) {
		$this->DB = CP::getDb( DB_PRIMARY );
	}

	/**
	 * Returns the total number of top-level comments (or replies to a given comment) that have been left.
	 *
	 * @param User $asUser User instance of a user viewing.
	 * @param int $inReplyTo [Optional] ID of a comment (changes from a top-level count to a reply count)
	 *
	 * @return int
	 */
	public function countComments( User $asUser, int $inReplyTo = 0 ) {
		$db = CP::getDb( DB_REPLICA );
		$results = $db->select(
			[ 'user_board' ],
			[ 'count(*) as total' ],
			[
				self::visibleClause( $asUser ),
				'ub_in_reply_to' => $inReplyTo,
				'ub_user_id' => $this->owner->getId()
			],
			__METHOD__
		);

		$row = $results->fetchRow();
		return $row['total'];
	}

	/**
	 * Generic comment retrieval utility function.  Automatically limits to viewable types.
	 *
	 * @param array $conditions SQL conditions applied to the user_board table query.
	 *                            Will be merged with existing conditions.
	 * @param User $asUser User viewing.
	 * @param int $startAt [Optional] Number of comments to skip when loading more.
	 * @param int $limit [Optional] Number of top-level items to return.
	 *
	 * @return array comments!
	 */
	private function getCommentsWithConditions( array $conditions, User $asUser, $startAt = 0, $limit = 100 ) {
		// Fetch top level comments.
		$results = $this->DB->select(
			[ 'user_board' ],
			[
				'*',
				'GREATEST(COALESCE(ub_date, 0), COALESCE(ub_last_reply, 0)) as date_sort'
			],
			array_merge( [
				self::visibleClause( $asUser ),
			], $conditions ),
			__METHOD__,
			[
				'ORDER BY'	=> 'date_sort DESC',
				'OFFSET'	=> $startAt,
				'LIMIT'		=> $limit
			]
		);

		$comments = [];
		// (for fast lookup of a comment by id when inserting replies)
		while ( $row = $results->fetchRow() ) {
			unset( $row['date_sort'] );
			$comments[] = new Comment( $row );
		}

		if ( empty( $comments ) ) {
			return $comments;
		}

		return $comments;
	}

	/**
	 * Returns a sql WHERE clause fragment limiting comments to the current user's visibility
	 *
	 * @param User $actor User object doing the viewing.
	 *
	 * @return string A single SQL condition entirely enclosed in parenthesis.
	 */
	public static function visibleClause( User $actor ) {
		if ( $actor->isAllowed( 'profile-comments-moderate' ) ) {
			// admins see everything
			$sql = '1=1';
		} else {
			$conditions = [];
			// Everyone sees public messages.
			$conditions[] = 'user_board.ub_type = 0';
			// See private if you are author or recipient.
			$conditions[] = sprintf(
				'user_board.ub_type = 1 AND (user_board.ub_user_id = %1$s OR user_board.ub_user_id_from = %1$s)',
				$actor->getId()
			);
			// See deleted if you are the author.
			$conditions[] = sprintf( 'user_board.ub_type = -1 AND user_board.ub_user_id_from = %1$s', $actor->getId() );
			$sql = '( (' . implode( ') OR (', $conditions ) . ') )';
		}
		return $sql;
	}

	/**
	 * Look up a single comment given a comment id (for display from a permalink)
	 *
	 * @param int $commentId id of a user board comment
	 *
	 * @return array An array of comment data in the same format as getComments.
	 *   array will be empty if comment is unknown, or not visible.
	 */
	public static function getPurgedCommentById( $commentId ) {
		$commentId = (int)$commentId;
		if ( $commentId < 1 ) {
			return [];
		}

		$DB = CP::getDb( DB_PRIMARY );
		$result = $DB->select(
			[ 'user_board_purge_archive' ],
			[ '*' ],
			[ 'ubpa_comment_id' => (int)$commentId ],
			__METHOD__
		);

		return $result->fetchRow();
	}

	/**
	 * Gets all comments on the board.
	 *
	 * @param User $asUser User viewing.
	 * @param int $startAt [Optional] number of comments to skip when loading more
	 * @param int $limit [Optional] number of top-level items to return
	 * @param int $maxAge [Optional] maximum age of comments (by number of days)
	 *
	 * @return array an array of comment data (text and user info)
	 */
	public function getComments( User $asUser, $startAt = 0, $limit = 100, $maxAge = 30 ) {
		$searchConditions = [
			'ub_in_reply_to' => 0,
			'ub_user_id' => $this->owner->getId()
		];
		if ( $maxAge >= 0 ) {
			$searchConditions[] = 'IFNULL(ub_last_reply, ub_date) >= ' .
				$this->DB->addQuotes( date( 'Y-m-d H:i:s', time() - $maxAge * 86400 ) );
		}
		return $this->getCommentsWithConditions( $searchConditions, $asUser, $startAt, $limit );
	}

	/**
	 * Add a public comment to the board
	 *
	 * @param string $commentText Comment Text
	 * @param User|null $fromUser User of user posting.
	 * @param int $inReplyTo [Optional] ID of a board post that this will be in reply to.
	 *
	 * @return int ID of the newly created comment, or 0 for failure
	 */
	public function addComment( string $commentText, User $fromUser = null, int $inReplyTo = 0 ) {
		if ( empty( $commentText ) ) {
			return false;
		}

		$comment = Comment::newWithOwner( $this->owner );
		if ( !$comment->canComment( $fromUser ) ) {
			return false;
		}

		$parentCommenter = null;
		if ( $inReplyTo ) {
			$parentComment = Comment::newFromId( $inReplyTo );
			$parentCommenter = $parentComment->getActorUser();
		}

		$comment->setMessage( $commentText );
		$comment->setActorUser( $fromUser );
		$comment->markAsPublic();
		$comment->setPostTimestamp( time() );
		$comment->setParentCommentId( $inReplyTo );
		$success = $comment->save();

		if ( $success ) {
			$newCommentId = $comment->getId();
		} else {
			$newCommentId = 0;
		}

		$notificationBroadcastFactory = MediaWikiServices::getInstance()
			->getService( NotificationBroadcastFactory::class );
		if ( $newCommentId ) {
			$action = $inReplyTo > 0 ? 'replied' : 'created';

			$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
			$hookContainer->run( 'CurseProfileAddComment', [ $fromUser, $this->owner, $inReplyTo, $commentText ] );
			if ( $inReplyTo ) {
				$hookContainer->run(
					'CurseProfileAddCommentReply',
					[ $fromUser, $this->owner, $inReplyTo, $commentText ]
				);
			}

			$fromUserTitle = Title::makeTitle( NS_USER_PROFILE, $fromUser->getName() );
			$toUserTitle = Title::makeTitle( NS_USER_PROFILE, $this->owner->getName() );
			$parentCommenterTitle = null;
			if ( $parentCommenter ) {
				$parentCommenterTitle = Title::makeTitle( NS_USER_PROFILE, $parentCommenter->getName() );
			}

			$commentPermanentLink = SpecialPage::getTitleFor(
				'CommentPermalink',
				$newCommentId,
				'comment' . $newCommentId
			)->getFullURL();

			$userNote = substr( $commentText, 0, 80 );

			if ( $inReplyTo > 0 ) {
				if ( $this->owner->getId() != $fromUser->getId() ) {
					if ( !$parentCommenter->equals( $this->owner ) ) {
						// We have to make two notifications.
						// One for the profile owner and one for the parent commenter.
						$broadcast = $notificationBroadcastFactory->newSingle(
							'user-interest-profile-comment-reply-other-self',
							$fromUser,
							$this->owner,
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
										$this->owner->getName()
									],
									[
										3,
										$parentCommenter->getName()
									],
									[
										4,
										$fromUserTitle->getFullURL()
									],
									[
										5,
										$toUserTitle->getFullURL()
									],
									[
										6,
										$parentCommenterTitle->getFullURL()
									]
								]
							]
						);
						if ( $broadcast ) {
							$broadcast->transmit();
						}
					}
				}
				$broadcast = $notificationBroadcastFactory->newSingle(
					'user-interest-profile-comment-reply-self-' .
					( $parentCommenter->equals( $this->owner ) ? 'self' : 'other' ),
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
								$this->owner->getName()
							],
							[
								3,
								$parentCommenter->getName()
							],
							[
								4,
								$fromUserTitle->getFullURL()
							],
							[
								5,
								$toUserTitle->getFullURL()
							],
							[
								6,
								$parentCommenterTitle->getFullURL()
							]
						]
					]
				);
				if ( $broadcast ) {
					$broadcast->transmit();
				}
			} else {
				$broadcast = $notificationBroadcastFactory->newSingle(
					'user-interest-profile-comment',
					$fromUser,
					$this->owner,
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
							],
							[
								3,
								$toUserTitle->getFullURL()
							],
							[
								4,
								$fromUserTitle->getFullURL()
							]
						]
					]
				);
				if ( $broadcast ) {
					$broadcast->transmit();
				}
			}

			// Insert an entry into the Log.
			$log = new ManualLogEntry( 'curseprofile', 'comment-' . $action );
			$log->setPerformer( $fromUser );
			$log->setTarget( $toUserTitle );
			$log->setComment( null );
			$log->setParameters(
				[
					'4:comment_id' => $newCommentId
				]
			);
			$logId = $log->insert();
			$log->publish( $logId );
		}

		return $newCommentId;
	}

	/**
	 * Replaces the text content of a comment.
	 *
	 * @param Comment $comment Comment to edit.
	 * @param User $actor User object of the user doing this action.
	 * @param string $message New text to use for the comment.
	 *
	 * @return bool Success
	 */
	public static function editComment( Comment $comment, User $actor, string $message ): bool {
		if ( !$comment->canEdit( $actor ) ) {
			return false;
		}

		$comment->setMessage( $message );
		$comment->setEditTimestamp( time() );
		if ( !$comment->save() ) {
			return false;
		}

		$toUser = $comment->getBoardOwnerUser();
		$title = Title::newFromURL( 'UserProfile:' . $toUser->getName() );
		$fromUser = $actor;

		$log = new ManualLogEntry( 'curseprofile', 'comment-edited' );
		$log->setPerformer( $fromUser );
		$log->setTarget( $title );
		$log->setComment( null );
		$log->setParameters( [ '4:comment_id' => $comment->getId() ] );
		$logId = $log->insert();
		$log->publish( $logId );

		return true;
	}

	/**
	 * Remove a comment from the board.
	 *
	 * @param Comment $comment Comment to remove.
	 * @param User $actor User object of the user doing this action.
	 *
	 * @return bool Success
	 */
	public static function removeComment( Comment $comment, User $actor ): bool {
		if ( !$comment->canRemove( $actor ) ) {
			return false;
		}

		$comment->markAsDeleted();
		$comment->setAdminActionTimestamp( time() );
		$comment->setAdminActedUser( $actor );
		if ( !$comment->save() ) {
			return false;
		}

		$toUser = $comment->getBoardOwnerUser();
		$title = Title::makeTitle( NS_USER_PROFILE, $toUser->getName() );

		$log = new ManualLogEntry( 'curseprofile', 'comment-deleted' );
		$log->setPerformer( $actor );
		$log->setTarget( $title );
		$log->setComment( null );
		$log->setParameters(
			[
				'4:comment_id' => $comment->getId()
			]
		);
		$logId = $log->insert();
		$log->publish( $logId );

		return true;
	}

	/**
	 * Restore a comment to the board.
	 *
	 * @param Comment $comment Comment to restore.
	 * @param User $actor User object of the user doing this action.
	 *
	 * @return bool Success
	 */
	public static function restoreComment( Comment $comment, User $actor ): bool {
		if ( !$comment->canRestore( $actor ) ) {
			return false;
		}

		$comment->markAsPublic();
		$comment->setAdminActionTimestamp( time() );
		$comment->setAdminActedUser( $actor );
		return $comment->save();
	}

	/**
	 * Permanently remove a comment from the board.
	 *
	 * @param Comment $comment Comment to purge from the database.
	 * @param User $actor User performing this action.
	 * @param string $reason
	 *
	 * @return bool Success
	 */
	public static function purgeComment( Comment $comment, User $actor, string $reason ) {
		if ( !$comment->canPurge( $actor ) ) {
			return false;
		}

		self::performPurge( $actor, $comment, $reason );
		$replies = $comment->getReplies( $actor );

		foreach ( $replies as $reply ) {
			self::performPurge( $actor, $reply, $reason );
		}
		return true;
	}

	/**
	 * Handle purging comments and logging the administrative action.
	 *
	 * @param User $actor User performing this action.
	 * @param Comment $comment
	 * @param string $reason
	 *
	 * @return bool Success.
	 */
	private static function performPurge( User $actor, Comment $comment, string $reason ) {
		$toUser = $comment->getBoardOwnerUser();
		$title = Title::makeTitle( NS_USER_PROFILE, $toUser->getName() );

		$db = CP::getDb( DB_PRIMARY );
		// save comment to archive for forensics
		$success = $db->insert(
			'user_board_purge_archive',
			[
				'ubpa_user_id' => $comment->getBoardOwnerUserId(),
				'ubpa_user_from_id' => $comment->getActorUserId(),
				'ubpa_admin_id' => $actor->getId(),
				'ubpa_comment_id' => $comment->getId(),
				'ubpa_comment' => $comment->getMessage(),
				'ubpa_reason' => $reason,
				'ubpa_purged_at' => date( 'Y-m-d H:i:s' ),
			],
			__METHOD__
		);

		if ( !$success ) {
			throw new Exception( "Unable to record this purge action. Purging comment canceled." );
		}

		// system log
		$log = new ManualLogEntry( 'curseprofile', 'comment-purged' );
		$log->setPerformer( $actor );
		$log->setTarget( $title );
		$log->setComment( $reason );
		$log->setParameters( [ '4:comment_id' => $comment->getId() ] );
		$logId = $log->insert();
		$log->publish( $logId );

		// Purge comment.
		return $db->delete(
			'user_board',
			[ 'ub_id' => $comment->getId() ]
		);
	}

	/**
	 * Send a comment to the moderation queue.
	 *
	 * @param Comment $comment The comment to report
	 * @param User $actor User performing this action.
	 *
	 * @return CommentReport|false Object or false for failure.
	 */
	public static function reportComment( Comment $comment, User $actor ) {
		if ( !$comment->canReport( $actor ) ) {
			return false;
		}

		if ( $comment ) {
			return CommentReport::newUserReport( $comment, $actor );
		}
		return false;
	}
}

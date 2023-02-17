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

namespace CurseProfile\Api;

use ApiMain;
use CurseProfile\Classes\Comment;
use CurseProfile\Classes\CommentBoard;
use CurseProfile\Classes\CommentDisplay;
use CurseProfile\Classes\Jobs\ResolveComment;
use DerivativeRequest;
use HydraApiBase;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Class that allows commenting actions to be performed by AJAX calls.
 */
class CommentApi extends HydraApiBase {
	public function __construct(
		ApiMain $main,
		$action,
		private UserFactory $userFactory,
		private UserOptionsLookup $userOptionsLookup,
		private UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct( $main, $action );
	}

	/** @inheritDoc */
	public function getActions(): array {
		return [
			'restore' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],

			'remove' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],

			'purge' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],

			'add' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'user_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true
					],
					'text' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'inReplyTo' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_DEFAULT => 0,
					],
				]
			],

			'getReplies' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'reason' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					]
				],
			],

			'getRaw' => [
				'tokenRequired' => false,
				'postRequired' => false,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],

			'edit' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'text' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
				],
			],

			'addToDefault' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'user_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true
					],
					'title' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'text' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'inReplyTo' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_DEFAULT => 0,
					],
				]
			],

			'report' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'params' => [
					'comment_id' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => true,
					],
				]
			],

			'resolveReport' => [
				'tokenRequired' => true,
				'postRequired' => true,
				'permissionRequired' => 'profile-comments-moderate',
				'params' => [
					'reportKey' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'byUser' => [
						ParamValidator::PARAM_TYPE => 'integer',
					],
					'withAction' => [ // string param with two possible enumerated values:
						ParamValidator::PARAM_TYPE => [ 'delete', 'dismiss' ],
						ParamValidator::PARAM_REQUIRED => true,
					],
				]
			],
		];
	}

	/**
	 * Adds a comment to a user's Curse Profile page or adds a new section on their talk page,
	 * depending on what the user has chosen as their default user page.
	 */
	public function doAddToDefault(): void {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $this->getMain()->getVal( 'user_id' ) );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			$this->dieWithError( [ 'comment-invaliduser' ] );
		}

		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
		$text = $this->getMain()->getVal( 'text' );
		$inReply = $this->getInt( 'inReplyTo' );

		if ( $this->userOptionsLookup->getIntOption( $user, 'comment-pref' ) ) {
			$board = new CommentBoard( $user );
			$commentSuccess = $board->addComment( $text, $this->getUser(), $inReply );
			$this->getResult()->addValue( null, 'result', ( $commentSuccess ? 'success' : 'failure' ) );
			return;
		}

		// the recommended way of editing a local article was with WikiPage::doEditContent
		// however there didn't seem to be an easy way to add a section rather than editing the entire content
		$params = new DerivativeRequest(
			$this->getRequest(),
			[
				'title' => 'User_talk:' . $user->getName(),
				'action' => 'edit',
				'section' => 'new',
				'summary' => $this->getMain()->getVal( 'title' ),
				'text' => $text,
				'token' => $this->getMain()->getVal( 'token' ),
			]
		);
		$api = new ApiMain( $params, true );
		$api->execute();
		// TODO: check the result object from the internal API call to determine success/failure status
		$this->getResult()->addValue( null, 'result', 'success' );
	}

	/**
	 * Adds a new comment to a user's comment board on their Curse Profile page
	 */
	public function doAdd(): void {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $this->getInt( 'user_id' ) );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			$this->getResult()->addValue( null, 'result', 'failure' );
			return;
		}

		$toUser = $this->userFactory->newFromUserIdentity( $userIdentity );
		$text = $this->getMain()->getVal( 'text' );
		$inReply = $this->getInt( 'inReplyTo' );

		$board = new CommentBoard( $toUser );
		$commentSuccess = $board->addComment( $text, $this->getUser(), $inReply );

		$this->getResult()->addValue( null, 'result', ( $commentSuccess ? 'success' : 'failure' ) );
	}

	/**
	 * Returns all replies to a specific comment
	 */
	public function doGetReplies(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		$replies = CommentDisplay::repliesTo( $comment, $this->getUser() );
		$this->getResult()->addValue( null, 'html', $replies );
	}

	public function doGetRaw(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		$this->getResult()->addValue(
			null,
			'text',
			$comment->canView( $this->getUser() ) ? $comment->getMessage() : ''
		);
	}

	public function doEdit(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( !$comment ) {
			$this->dieWithError( [ 'comment-invalidaction' ] );
		}

		$text = $this->getMain()->getVal( 'text' );
		$success = CommentBoard::editComment( $comment, $this->getUser(), $text );
		$this->getResult()->addValue( null, 'result', $success ? 'success' : 'failure' );
		// add parsed text to result
		$this->getResult()->addValue( null, 'parsedContent', CommentDisplay::sanitizeComment( $text ) );
	}

	public function doRestore(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( !$comment ) {
			$this->dieWithError( [ 'comment-invalidaction' ] );
		}

		$success = CommentBoard::restoreComment( $comment, $this->getUser() );
		$this->getResult()->addValue( null, 'result', $success ? 'success' : 'failure' );
		$this->getResult()->addValue( null, 'html', $this->msg( 'comment-adminremoved' ) );
	}

	public function doRemove(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( !$comment ) {
			$this->dieWithError( [ 'comment-invalidaction' ] );
		}

		$success = CommentBoard::removeComment( $comment, $this->getUser() );
		$this->getResult()->addValue( null, 'result', $success ? 'success' : 'failure' );
		$this->getResult()->addValue( null, 'html', $this->msg( 'comment-adminremoved' ) );
	}

	public function doPurge(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( !$comment ) {
			$this->dieWithError( [ 'comment-invalidaction' ] );
		}

		$reason = $this->getMain()->getVal( 'reason' );
		$success = CommentBoard::purgeComment( $comment, $this->getUser(), $reason );
		$this->getResult()->addValue( null, 'result', $success ? 'success' : 'failure' );
	}

	public function doReport(): void {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( !$comment ) {
			$this->dieWithError( [ 'comment-invalidaction' ] );
		}

		$result = CommentBoard::reportComment( $comment, $this->getUser() );
		$this->getResult()->addValue( null, 'result', $result ? 'success' : 'error' );
	}

	/**
	 * Schedule job to resolve Report API End Point
	 *
	 * @return bool Success
	 */
	public function doResolveReport(): bool {
		if ( !$this->getUser()->isRegistered() ) {
			return false;
		}

		$reportKey = $this->getMain()->getVal( 'reportKey' );

		ResolveComment::queue(
			$reportKey,
			$this->getMain()->getVal( 'withAction' ),
			$this->getInt( 'byUser', $this->getUser()->getId() )
		);
		$this->getResult()->addValue( null, 'result', 'queued' );
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}
}

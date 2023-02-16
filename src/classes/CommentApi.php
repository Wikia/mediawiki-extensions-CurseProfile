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

use ApiMain;
use CurseProfile\Classes\Jobs\ResolveComment;
use DerivativeRequest;
use HydraApiBase;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Class that allows commenting actions to be performed by AJAX calls.
 */
class CommentApi extends HydraApiBase {
	/**
	 * Get Actions
	 *
	 * @return array
	 */
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
	public function doAddToDefault() {
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->getMain()->getVal( 'user_id' ) );
		if ( !$user || $user->isAnon() ) {
			return $this->dieWithError( [ 'comment-invaliduser' ] );
		}
		$text = $this->getMain()->getVal( 'text' );
		$inreply = $this->getInt( 'inReplyTo' );

		if ( MediaWikiServices::getInstance()->getUserOptionsLookup()->getIntOption( $user, 'comment-pref' ) ) {
			$board = new CommentBoard( $user );
			$commentSuccess = $board->addComment( $text, $this->getUser(), $inreply );
			$this->getResult()->addValue( null, 'result', ( $commentSuccess ? 'success' : 'failure' ) );
		} else {
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
	}

	/**
	 * Adds a new comment to a user's comment board on their Curse Profile page
	 */
	public function doAdd() {
		$toUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->getInt( 'user_id' ) );
		if ( !$toUser || !$toUser->isAnon() ) {
				$this->getResult()->addValue( null, 'result', 'failure' );
				return;
		}

		$text = $this->getMain()->getVal( 'text' );
		$inreply = $this->getInt( 'inReplyTo' );

		$board = new CommentBoard( $toUser );
		$commentSuccess = $board->addComment( $text, $this->getUser(), $inreply );

		$this->getResult()->addValue( null, 'result', ( $commentSuccess ? 'success' : 'failure' ) );
	}

	/**
	 * Returns all replies to a specific comment
	 */
	public function doGetReplies() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		$replies = CommentDisplay::repliesTo( $comment, $this->getUser() );
		$this->getResult()->addValue( null, 'html', $replies );
	}

	public function doGetRaw() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		$this->getResult()->addValue( null, 'text', $comment->canView( $this->getUser() ) ? $comment->getMessage() : '' );
	}

	public function doEdit() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		$text = $this->getMain()->getVal( 'text' );
		if ( $comment ) {
			$res = CommentBoard::editComment( $comment, $this->getUser(), $text );
			$this->getResult()->addValue( null, 'result', 'success' );
			// add parsed text to result
			$this->getResult()->addValue( null, 'parsedContent', CommentDisplay::sanitizeComment( $text ) );
		} else {
			$this->dieWithError( [ 'comment-invalidaction' ] );
		}
	}

	public function doRestore() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( $comment ) {
			CommentBoard::restoreComment( $comment, $this->getUser() );
			$this->getResult()->addValue( null, 'result', 'success' );
			$this->getResult()->addValue( null, 'html', wfMessage( 'comment-adminremoved' ) );
		} else {
			return $this->dieWithError( [ 'comment-invalidaction' ] );
		}
	}

	public function doRemove() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( $comment ) {
			CommentBoard::removeComment( $comment, $this->getUser() );
			$this->getResult()->addValue( null, 'result', 'success' );
			$this->getResult()->addValue( null, 'html', wfMessage( 'comment-adminremoved' ) );
		} else {
			return $this->dieWithError( [ 'comment-invalidaction' ] );
		}
	}

	public function doPurge() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		$reason = $this->getMain()->getVal( 'reason' );
		if ( $comment ) {
			CommentBoard::purgeComment( $comment, $this->getUser(), $reason );
			$this->getResult()->addValue( null, 'result', 'success' );
		} else {
			return $this->dieWithError( [ 'comment-invalidaction' ] );
		}
	}

	public function doReport() {
		$comment = Comment::newFromId( $this->getInt( 'comment_id' ) );
		if ( $comment ) {
			$result = CommentBoard::reportComment( $comment, $this->getUser() );
			$this->getResult()->addValue( null, 'result', $result ? 'success' : 'error' );
		} else {
			return $this->dieWithError( [ 'comment-invalidaction' ] );
		}
	}

	/**
	 * Resolve Report API End Point
	 *
	 * @return bool Success
	 */
	public function doResolveReport() {
		if ( !$this->getUser()->isRegistered() ) {
			return false;
		}

		$reportKey = $this->getMain()->getVal( 'reportKey' );
		$jobArgs = [
			'reportKey' => $reportKey,
			'action' => $this->getMain()->getVal( 'withAction' ),
			'byUser' => $this->getInt( 'byUser', $this->getUser()->getId() ),
		];

		ResolveComment::queue( $jobArgs );
		$this->getResult()->addValue( null, 'result', 'queued' );
		return true;
	}

	/**
	 * Indicates whether this module requires write mode
	 *
	 * @return bool
	 */
	public function isWriteMode() {
		return true;
	}
}

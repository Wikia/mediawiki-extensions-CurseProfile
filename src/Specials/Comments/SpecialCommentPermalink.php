<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Specials\Comments;

use CurseProfile\Classes\Comment;
use CurseProfile\Classes\CommentBoard;
use CurseProfile\Classes\CommentDisplay;
use CurseProfile\Classes\ProfileData;
use CurseProfile\Templates\TemplateCommentBoard;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use UnlistedSpecialPage;

class SpecialCommentPermalink extends UnlistedSpecialPage {
	public function __construct( private UserFactory $userFactory ) {
		parent::__construct( 'CommentPermalink' );
	}

	/**
	 * @inheritDoc
	 * @param ?string $subPage commentId
	 */
	public function execute( $subPage ) {
		$output = $this->getOutput();
		$this->setHeaders();

		if ( empty( $subPage ) ) {
			$output->setPageTitle( $this->msg( 'commentboard-invalid-title' )->plain() );
			$output->addWikiMsg( 'commentboard-invalid' );
			return;
		}

		$commentId = (int)$subPage;

		// checks if comment exists and if wgUser can view it
		$comment = Comment::newFromId( $commentId );

		if ( !$comment || !$comment->canView( $this->getUser() ) ) {
			$purged = CommentBoard::getPurgedCommentById( $commentId );
			if ( $purged ) {
				$user = $this->userFactory->newFromId( $purged['ubpa_user_id'] );
				$adminUser = $this->userFactory->newFromId( $purged['ubpa_admin_id'] );
				$output->setPageTitle( $this->msg( 'commentboard-purged-title', $user->getName() )->plain() );
				$output->addWikiMsg(
					'commentboard-purged',
					$purged['ubpa_reason'],
					$adminUser->getName(),
					( new ProfileData( $adminUser ) )->getProfilePageUrl()
				);
				$output->setStatusCode( 404 );
				return;
			}

			$output->setPageTitle( $this->msg( 'commentboard-invalid-title' )->plain() );
			$output->addWikiMsg( 'commentboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		$owner = $comment->getBoardOwnerUser();

		$output->setPageTitle( $this->msg( 'commentboard-permalink-title', $owner->getName() )->plain() );
		$output->addModuleStyles( [ 'ext.curseprofile.comments.styles', 'ext.hydraCore.font-awesome.styles' ] );
		$output->addModules( [ 'ext.curseprofile.comments.scripts' ] );
		$templateCommentBoard = new TemplateCommentBoard();

		$output->addHTML( $templateCommentBoard->permalinkHeader( $owner, $output->getPageTitle() ) );

		// display single comment while highlighting the selected ID
		$output->addHTML(
			'<div class="comments">' .
			CommentDisplay::newCommentForm( $owner, true ) .
			CommentDisplay::singleComment( $comment, $comment->getId() ) .
			'</div>'
		);
	}
}

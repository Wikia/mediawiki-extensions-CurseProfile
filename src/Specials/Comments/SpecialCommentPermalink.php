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
use UnlistedSpecialPage;

class SpecialCommentPermalink extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'CommentPermalink' );
	}

	/**
	 * Show the special page
	 *
	 * @param string $commentId extra string added to the page request path (/Special:CommentPermalink/12345) -> "12345"
	 *
	 * @return void
	 */
	public function execute( $commentId ) {
		$output = $this->getOutput();
		$this->setHeaders();

		// checks if comment exists and if wgUser can view it
		$comment = Comment::newFromId( $commentId );

		if ( !$comment || !$comment->canView( $this->getUser() ) ) {
			$purged = CommentBoard::getPurgedCommentById( $commentId );
			if ( $purged ) {
				$userFactory = MediaWikiServices::getInstance()->getUserFactory();
				$user = $userFactory->newFromId( $purged['ubpa_user_id'] );
				$user->load();
				$admin_user = $userFactory->newFromId( $purged['ubpa_admin_id'] );
				$admin_user->load();
				$output->setPageTitle( $this->msg( 'commentboard-purged-title', $user->getName() )->plain() );
				$output->addWikiMsg(
					'commentboard-purged',
					$purged['ubpa_reason'],
					$admin_user->getName(),
					( new ProfileData( $admin_user ) )->getProfilePageUrl()
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
		$templateCommentBoard = new TemplateCommentBoard;

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

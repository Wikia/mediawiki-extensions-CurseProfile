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

use CurseProfile\Classes\CommentBoard;
use CurseProfile\Templates\TemplateCommentBoard;
use HydraCore;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use SpecialPage;
use UnlistedSpecialPage;

class SpecialCommentBoard extends UnlistedSpecialPage {
	public function __construct( private UserFactory $userFactory, private UserIdentityLookup $userIdentityLookup ) {
		parent::__construct( 'CommentBoard' );
	}

	/**
	 * @param ?string $subPage userId/username - missing or mismatching username will be fixed automatically
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		if ( empty( $subPage ) ) {
			$output->addWikiMsg( 'commentboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		// parse path segment for special page url similar to:
		// /Special:CommentBoard/4/Cathadan
		[ $userId, $userName ] = explode( '/', $subPage );
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( (int)$userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			$output->addWikiMsg( 'commentboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		$user = $this->userFactory->newFromUserIdentity( $userIdentity );

		// Fix missing or incorrect username segment in the path
		if ( $user->getTitleKey() !== $userName ) {
			$fixedPath = SpecialPage::getSafeTitleFor( 'CommentBoard', "$userId/{$user->getTitleKey()}" )->getFullURL();
			// Preserve query params
			$query = $request->getRawQueryString();
			$output->redirect( empty( $query ) ? $fixedPath : "$fixedPath?$query" );
			return;
		}

		$start = $request->getInt( 'st' );
		$itemsPerPage = 50;
		$output->setPageTitle( $this->msg( 'commentboard-title', $user->getName() )->plain() );
		$output->addModuleStyles( [
			'ext.curseprofile.comments.styles',
			'ext.hydraCore.pagination.styles',
			'ext.hydraCore.font-awesome.styles'
		] );
		$output->addModules( [ 'ext.curseprofile.comments.scripts' ] );
		$templateCommentBoard = new TemplateCommentBoard();

		$output->addHTML( $templateCommentBoard->header( $user, $output->getPageTitle() ) );

		$board = new CommentBoard( $user, CommentBoard::BOARDTYPE_ARCHIVES );

		$total = $board->countComments( $this->getUser() );
		if ( $total == 0 ) {
			$output->addWikiMsg( 'commentboard-empty', $user->getName() );
			return;
		}

		$comments = $board->getComments( $this->getUser(), $start, $itemsPerPage, -1 );
		$pagination = HydraCore::generatePaginationHtml( $this->getFullTitle(), $total, $itemsPerPage, $start );

		$output->addHTML( $templateCommentBoard->comments( $comments, $user, $pagination ) );
	}
}

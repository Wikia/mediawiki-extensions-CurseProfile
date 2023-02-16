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
use UnlistedSpecialPage;

class SpecialCommentBoard extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'CommentBoard' );
	}

	/**
	 * Show the special page
	 *
	 * @param string $path Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $path ) {
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$this->setHeaders();
		if ( empty( $path ) ) {
			$wgOut->addWikiMsg( 'commentboard-invalid' );
			$wgOut->setStatusCode( 404 );
			return;
		}

		// parse path segment for special page url similar to:
		// /Special:CommentBoard/4/Cathadan
		list( $userId, $user_name ) = explode( '/', $path );
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId );
		$user->load();
		if ( !$user || $user->isAnon() ) {
			$wgOut->addWikiMsg( 'commentboard-invalid' );
			$wgOut->setStatusCode( 404 );
			return;
		}

		// Fix missing or incorrect username segment in the path
		if ( $user->getTitleKey() != $user_name ) {
			$fixedPath = '/Special:CommentBoard/' . $userId . '/' . $user->getTitleKey();
			if ( !empty( $_SERVER['QUERY_STRING'] ) ) {
				// don't destroy any extra params
				$fixedPath .= '?' . $_SERVER['QUERY_STRING'];
			}
			$wgOut->redirect( $fixedPath );
			return;
		}

		$start = $wgRequest->getInt( 'st' );
		$itemsPerPage = 50;
		$wgOut->setPageTitle( wfMessage( 'commentboard-title', $user->getName() )->plain() );
		$wgOut->addModuleStyles( [ 'ext.curseprofile.comments.styles', 'ext.hydraCore.pagination.styles', 'ext.hydraCore.font-awesome.styles' ] );
		$wgOut->addModules( [ 'ext.curseprofile.comments.scripts' ] );
		$templateCommentBoard = new TemplateCommentBoard;

		$wgOut->addHTML( $templateCommentBoard->header( $user, $wgOut->getPageTitle() ) );

		$board = new CommentBoard( $user, CommentBoard::BOARDTYPE_ARCHIVES );

		$total = $board->countComments( $this->getUser() );
		if ( $total == 0 ) {
			$wgOut->addWikiMsg( 'commentboard-empty', $user->getName() );
			return;
		}

		$comments = $board->getComments( $this->getUser(), $start, $itemsPerPage, -1 );
		$pagination = HydraCore::generatePaginationHtml( $this->getFullTitle(), $total, $itemsPerPage, $start );

		$wgOut->addHTML( $templateCommentBoard->comments( $comments, $user, $pagination ) );
	}
}

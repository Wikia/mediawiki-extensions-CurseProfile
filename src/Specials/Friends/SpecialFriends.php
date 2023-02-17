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

namespace CurseProfile\Specials\Friends;

use CurseProfile\Classes\Friendship;
use CurseProfile\Templates\TemplateManageFriends;
use HydraCore;
use MediaWiki\MediaWikiServices;
use Title;
use UnlistedSpecialPage;

/**
 * Special page that lists the friends a user has.
 * Redirects to ManageFriends when viewing one's own friends page.
 */
class SpecialFriends extends UnlistedSpecialPage {
	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'Friends' );
	}

	/**
	 * Show the special page
	 *
	 * @param string $path - Mixed: parameter(s) passed to the page or null.
	 */
	public function execute( $path ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		if ( empty( $path ) ) {
			$output->addWikiMsg( 'friendsboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		// parse path segment for special page url similar to:
		// /Special:Friends/4/Cathadan
		$parts = explode( '/', $path );
		$userId = isset( $parts[0] ) ? intval( $parts[0] ) : 0;
		$userName = isset( $parts[1] ) ? $parts[1] : null;

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId );
		$user->load();
		if ( !$user || $user->isAnon() ) {
			$output->addWikiMsg( 'friendsboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		// when viewing your own friends list, use the manage page
		if ( $this->getUser()->getId() == $user->getId() ) {
			$specialManageFriends = Title::newFromText( 'Special:ManageFriends' );
			$output->redirect( $specialManageFriends->getFullURL() );
			return;
		}

		// Fix missing or incorrect username segment in the path
		if ( $user->getTitleKey() != $userName ) {
			$specialFriends = Title::newFromText( 'Special:Friends/' . $userId . '/' . $user->getTitleKey() );
			if ( !empty( $_SERVER['QUERY_STRING'] ) ) {
				// don't destroy any extra params
				$query = '?' . $_SERVER['QUERY_STRING'];
			}
			$output->redirect( $specialFriends->getFullURL() . $query );
			return;
		}

		$start = $request->getInt( 'st' );
		$itemsPerPage = 25;
		$output->setPageTitle( $this->msg( 'friendsboard-title', $user->getName() )->plain() );
		$output->addModuleStyles( [
			'ext.curseprofile.profilepage.styles',
			'ext.hydraCore.pagination.styles',
			'ext.curseprofile.customskin.styles',
			'ext.curseprofile.comments.styles',
			'ext.hydraCore.font-awesome.styles'
		] );
		$output->addModules( [ 'ext.curseprofile.profilepage.scripts' ] );
		$templateManageFriends = new TemplateManageFriends;

		$f = new Friendship( $user );

		$friendTypes = $f->getFriends();
		$pagination = HydraCore::generatePaginationHtml(
			$this->getFullTitle(),
			count( $friendTypes['friends'] ),
			$itemsPerPage,
			$start
		);

		$output->addHTML(
			$templateManageFriends->display( $friendTypes['friends'], $pagination, $itemsPerPage, $start )
		);
	}
}

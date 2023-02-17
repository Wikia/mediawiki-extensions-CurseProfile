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
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use SpecialPage;
use Title;
use UnlistedSpecialPage;

/**
 * Special page that lists the friends a user has.
 * Redirects to ManageFriends when viewing one's own friends page.
 */
class SpecialFriends extends UnlistedSpecialPage {
	public function __construct( private UserFactory $userFactory, private UserIdentityLookup $userIdentityLookup ) {
		parent::__construct( 'Friends' );
	}

	/**
	 * @inheritDoc
	 * @param ?string $subPage userId/userName - missing or mismatching username will be fixed automatically
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		if ( empty( $subPage ) ) {
			$output->addWikiMsg( 'friendsboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		// parse path segment for special page url similar to:
		// /Special:Friends/4/Cathadan
		[ $userId, $userName ] = explode( '/', $subPage );

		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( (int)$userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			$output->addWikiMsg( 'friendsboard-invalid' );
			$output->setStatusCode( 404 );
			return;
		}

		// when viewing your own friends list, use the manage page
		if ( $this->getUser()->getId() === $userIdentity->getId() ) {
			$output->redirect( SpecialPage::getTitleFor( 'ManageFriends' )->getFullURL() );
			return;
		}

		$user = $this->userFactory->newFromUserIdentity( $userIdentity );

		// Fix missing or incorrect username segment in the path
		if ( $user->getTitleKey() != $userName ) {
			$fixedPath = SpecialPage::getTitleFor( 'Friends', "$userId/{$user->getTitleKey()}" )->getFullURL();
			// Preserve query params
			$query = $request->getRawQueryString();
			$output->redirect( empty( $query ) ? $fixedPath : "$fixedPath?$query" );
			return;
		}

		$output->setPageTitle( $this->msg( 'friendsboard-title', $user->getName() )->plain() );
		$output->addModuleStyles( [
			'ext.curseprofile.profilepage.styles',
			'ext.hydraCore.pagination.styles',
			'ext.curseprofile.customskin.styles',
			'ext.curseprofile.comments.styles',
			'ext.hydraCore.font-awesome.styles'
		] );
		$output->addModules( [ 'ext.curseprofile.profilepage.scripts' ] );
		$templateManageFriends = new TemplateManageFriends();

		$start = $request->getInt( 'st' );
		$itemsPerPage = 25;
		$friendship = new Friendship( $user );
		$friendTypes = $friendship->getFriends();
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

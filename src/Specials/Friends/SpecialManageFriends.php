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
use SpecialPage;
use UserNotLoggedIn;

/**
 * Special page that allows one to manage their friends.
 * In addition to listing current friends, shows pending requests, both incoming and outgoing.
 * Also allows for friend requests to be sent directly by name.
 */
class SpecialManageFriends extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageFriends' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @inheritDoc
	 * @param ?string $subPage unused
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->outputHeader();
		$request = $this->getRequest();
		$output = $this->getOutput();

		// Fix missing or incorrect username segment in the path
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			throw new UserNotLoggedIn(
				'exception-nologinreturn-text',
				'exception-nologin',
				[ 'Special:ManageFriends' ]
			);
		}

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

		$output->addHTML( $templateManageFriends->manage( $user, $friendTypes, $itemsPerPage, $start ) );
	}
}

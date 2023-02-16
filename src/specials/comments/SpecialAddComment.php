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

namespace CurseProfile\Specials\Comments;

use CurseProfile\Classes\CommentBoard;
use CurseProfile\Classes\ProfileData;
use MediaWiki\MediaWikiServices;
use UnlistedSpecialPage;

class SpecialAddComment extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'AddComment' );
	}

	/**
	 * Show the special page
	 *
	 * @param string $toUserId Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $toUserId ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$user = $output->getUser();

		$toUser = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $toUserId );
		$tokenSet = $this->getContext()->getCsrfTokenSet();
		if ( $request->wasPosted() && $tokenSet->matchToken( $request->getVal( 'token' ) ) ) {
			$board = new CommentBoard( $toUser );
			$board->addComment( $request->getVal( 'message' ), $user, $request->getInt( 'inreplyto' ) );
		}

		$output->redirect( ( new ProfileData( $toUser ) )->getProfilePageUrl() );
	}
}

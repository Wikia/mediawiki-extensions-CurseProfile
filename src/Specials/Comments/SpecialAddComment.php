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
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\User\UserFactory;
use Wikimedia\Timestamp\TimestampException;

class SpecialAddComment extends UnlistedSpecialPage {
	public function __construct( private readonly UserFactory $userFactory ) {
		parent::__construct( 'AddComment' );
	}

	/**
	 * @throws TimestampException
	 */
	public function execute( $subPage ): void {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$user = $output->getUser();

		if ( empty( $subPage ) || !is_numeric( $subPage ) ) {
			$output->addWikiMsg( 'comment-invaliduser' );
			return;
		}

		$toUser = $this->userFactory->newFromId( (int)$subPage );
		$tokenSet = $this->getContext()->getCsrfTokenSet();
		if ( $request->wasPosted() && $tokenSet->matchToken( $request->getVal( 'token' ) ) ) {
			$board = new CommentBoard( $toUser );
			$board->addComment( $request->getVal( 'message' ), $user, $request->getInt( 'inreplyto' ) );
		}

		$output->redirect( ( new ProfileData( $toUser ) )->getProfilePageUrl() );
	}
}

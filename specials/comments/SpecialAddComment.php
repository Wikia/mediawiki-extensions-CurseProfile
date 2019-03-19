<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GNU General Public License v2.0 or later
 * @package   CurseProfile
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use SpecialPage;
use UnlistedSpecialPage;

class SpecialAddComment extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct('AddComment');
	}

	/**
	 * Show the special page
	 *
	 * @param string $toUser Mixed: parameter(s) passed to the page or null
	 */
	public function execute($toUser) {
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$wgUser = $wgOut->getUser();
		// maybe we will want to redirect to the comment permalink in some cases?
		// if that ever comes up, just update with the correct logic here
		$redirectToComment = false;

		if ($wgRequest->wasPosted() && $wgUser->matchEditToken($wgRequest->getVal('token'))) {
			$board = new CommentBoard($toUser);
			$newCommentId = $board->addComment($wgRequest->getVal('message'), null, $wgRequest->getVal('inreplyto'));
		}

		if ($newCommentId && $redirectToComment) {
			$wgOut->redirect(SpecialPage::getTitleFor('CommentPermalink', $newCommentId)->getFullURL());
		} else {
			$wgOut->redirect((new ProfileData($toUser))->getProfilePageUrl());
		}
	}
}

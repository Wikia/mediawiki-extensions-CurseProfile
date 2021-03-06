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
**/

namespace CurseProfile;

use UnlistedSpecialPage;
use User;

class SpecialAddComment extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct('AddComment');
	}

	/**
	 * Show the special page
	 *
	 * @param string $toUser Mixed: parameter(s) passed to the page or null
	 */
	public function execute($toUserId) {
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$wgUser = $wgOut->getUser();

		$toUser = User::newFromId($toUserId);
		if ($wgRequest->wasPosted() && $wgUser->matchEditToken($wgRequest->getVal('token'))) {
			$board = new CommentBoard($toUser);
			$newCommentId = $board->addComment($wgRequest->getVal('message'), $wgUser, $wgRequest->getInt('inreplyto'));
		}

		$wgOut->redirect((new ProfileData($toUser))->getProfilePageUrl());
	}
}

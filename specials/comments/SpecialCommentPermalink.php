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
**/

namespace CurseProfile;

use TemplateCommentBoard;
use UnlistedSpecialPage;
use User;

class SpecialCommentPermalink extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct('CommentPermalink');
	}

	/**
	 * Show the special page
	 *
	 * @param string $commentId extra string added to the page request path (/Special:CommentPermalink/12345) -> "12345"
	 *
	 * @return void
	 */
	public function execute($commentId) {
		$wgOut = $this->getOutput();
		$this->setHeaders();

		// checks if comment exists and if wgUser can view it
		$comment = CommentBoard::getCommentById($commentId);

		if (!$comment) {
			$purged = CommentBoard::getPurgedCommentById($commentId);
			if ($purged) {
				$user = User::newFromId($purged['ubpa_user_id']);
				$user->load();
				$admin_user = User::newFromId($purged['ubpa_admin_id']);
				$admin_user->load();
				$wgOut->setPageTitle(wfMessage('commentboard-purged-title', $user->getName())->plain());
				$wgOut->addWikiMsg('commentboard-purged', $purged['ubpa_reason'], $admin_user->getName(), (new ProfileData($admin_user))->getProfilePageUrl());
				$wgOut->setStatusCode(404);
				return;
			}
		}

		if (empty($comment)) {
			$wgOut->setPageTitle(wfMessage('commentboard-invalid-title')->plain());
			$wgOut->addWikiMsg('commentboard-invalid');
			$wgOut->setStatusCode(404);
			return;
		}

		$user = User::newFromId($comment[0]['ub_user_id']);
		$user->load();

		$wgOut->setPageTitle(wfMessage('commentboard-permalink-title', $user->getName())->plain());
		$wgOut->addModuleStyles(['ext.curseprofile.comments.styles']);
		$wgOut->addModules(['ext.curseprofile.comments.scripts']);
		$templateCommentBoard = new TemplateCommentBoard;

		$wgOut->addHTML($templateCommentBoard->permalinkHeader($user, $wgOut->getPageTitle()));

		// display single comment while highlighting the selected ID
		$wgOut->addHTML('<div class="comments">' . CommentDisplay::newCommentForm($user->getId(), true) . CommentDisplay::singleComment($comment[0], $commentId) . '</div>');
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GNU General Public License v2.0 or later
 * @package   CurseProfile
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
	 */
	public function execute($commentId) {
		$wgOut = $this->getOutput();
		$this->setHeaders();

		// checks if comment exists and if wgUser can view it
		$comment = CommentBoard::getCommentById($commentId);
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

<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class SpecialCommentPermalink extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'CommentPermalink' );
	}

	/**
	 * Show the special page
	 *
	 * @param $comment_id string: extra string added to the page request path (/Special:CommentPermalink/12345) -> "12345"
	 */
	public function execute( $comment_id ) {
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$this->setHeaders();

		// checks if comment exists and if wgUser can view it
		$comment = CommentBoard::getCommentById($comment_id);
		if (empty($comment)) {
			$wgOut->setPageTitle('commentboard-invalid-title');
			$wgOut->addWikiMsg('commentboard-invalid');
			$wgOut->setStatusCode(404);
			return;
		}

		$user = \User::newFromId($comment[0]['ub_user_id']);
		$user->load();

		$mouse = CP::loadMouse(['output' => 'mouseOutputOutput']);
		$wgOut->setPageTitle(wfMessage('commentboard-permalink-title', $user->getName())->plain());
		$wgOut->addModules('ext.curseprofile.comments');
		$mouse->output->addTemplateFolder(dirname(dirname(__DIR__)).'/templates');
		$mouse->output->loadTemplate('commentboard');

		$wgOut->addHTML($mouse->output->commentboard->permalinkHeader($user, $wgOut->getPageTitle()));

		// display single comment while highlighting the selected ID
		$wgOut->addHTML('<div class="comments">'.CommentDisplay::newCommentForm($user->getId(), true).CommentDisplay::singleComment($comment[0], $comment_id).'</div>');

		return;
	}
}

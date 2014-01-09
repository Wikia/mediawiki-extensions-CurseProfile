<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class SpecialAddComment extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'AddComment' );
	}

	/**
	 * Show the special page
	 *
	 * @param $params Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $toUser ) {
		global $wgUser;
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();

		if ($wgRequest->wasPosted()) {
			$user = \User::newFromId($toUser);
			$board = new CommentBoard($toUser);
			// $wgOut->addHTML('result: <pre>'.var_export($friendship->sendRequest(CP::curseIDfromUserID($toUser)), true));
			$board->addComment($wgRequest->getVal('message'));
		}
		$wgOut->redirect('/User:'.urlencode($user->getName()));
		return;
	}
}

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

/**
 * Special page that lists the friends a user has.
 * Redirects to ManageFriends when viewing one's own friends page.
 */
class SpecialFriends extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'Friends' );
	}

	/**
	 * Show the special page
	 *
	 * @param $params Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $path ) {
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$this->setHeaders();
		if (empty($path)) {
			$wgOut->addWikiMsg('commentboard-invalid');
			$wgOut->setStatusCode(404);
			return;
		}

		// parse path segment for special page url similar to:
		// /Special:Friends/4/Cathadan
		list($user_id, $user_name) = explode('/', $path);
		$user = \User::newFromId($user_id);
		$user->load();
		if (!$user || $user->isAnon()) {
			$wgOut->addWikiMsg('commentboard-invalid');
			$wgOut->setStatusCode(404);
			return;
		}

		// when viewing your own friends list, use the manage page
		if ($this->getUser()->getId() == $user->getId()) {
			$wgOut->redirect('/Special:ManageFriends');
			return;
		}

		// Fix missing or incorrect username segment in the path
		if ($user->getTitleKey() != $user_name) {
			$fixedPath = '/Special:Friends/'.$user_id.'/'.$user->getTitleKey();
			if (!empty($_SERVER['QUERY_STRING'])) { // don't destroy any extra params
				$fixedPath .= '?'.$_SERVER['QUERY_STRING'];
			}
			$wgOut->redirect($fixedPath);
			return;
		}

		// $start = $wgRequest->getInt('st');
		// $itemsPerPage = 50;
		$mouse = CP::loadMouse(['output' => 'mouseOutputOutput']);
		$wgOut->setPageTitle(wfMessage('friendsboard-title', $user->getName())->plain());
		$wgOut->addModules('ext.curseprofile.profilepage');
		$mouse->output->addTemplateFolder(dirname(dirname(__DIR__)).'/templates');
		$mouse->output->loadTemplate('managefriends');

		$f = new Friendship($user->curse_id);

		$friends = $f->getFriends();

		// $comments = $board->getComments(null, $start, $itemsPerPage, -1);
		// $pagination = Curse::generatePaginationHtml($total, $itemsPerPage, $start);

		$wgOut->addHTML($mouse->output->managefriends->display($friends));

		return;
	}
}

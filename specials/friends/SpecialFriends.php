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

use CentralIdLookup;
use HydraCore;
use TemplateManageFriends;
use Title;
use UnlistedSpecialPage;
use User;

/**
 * Special page that lists the friends a user has.
 * Redirects to ManageFriends when viewing one's own friends page.
 */
class SpecialFriends extends UnlistedSpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct('Friends');
	}

	/**
	 * Show the special page
	 *
	 * @access public
	 * @param  string $path - Mixed: parameter(s) passed to the page or null.
	 */
	public function execute($path) {
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();
		$this->setHeaders();
		if (empty($path)) {
			$wgOut->addWikiMsg('friendsboard-invalid');
			$wgOut->setStatusCode(404);
			return;
		}

		// parse path segment for special page url similar to:
		// /Special:Friends/4/Cathadan
		list($user_id, $user_name) = explode('/', $path);
		$user = User::newFromId($user_id);
		$user->load();
		if (!$user || $user->isAnon()) {
			$wgOut->addWikiMsg('friendsboard-invalid');
			$wgOut->setStatusCode(404);
			return;
		}

		// when viewing your own friends list, use the manage page
		if ($this->getUser()->getId() == $user->getId()) {
			$specialManageFriends = Title::newFromText('Special:ManageFriends');
			$wgOut->redirect($specialManageFriends->getFullURL());
			return;
		}

		// Fix missing or incorrect username segment in the path
		if ($user->getTitleKey() != $user_name) {
			$specialFriends = Title::newFromText('Special:Friends/' . $user_id . '/' . $user->getTitleKey());
			if (!empty($_SERVER['QUERY_STRING'])) {
				// don't destroy any extra params
				$query = '?' . $_SERVER['QUERY_STRING'];
			}
			$wgOut->redirect($specialFriends->getFullURL() . $query);
			return;
		}

		$start = $wgRequest->getInt('st');
		$itemsPerPage = 25;
		$wgOut->setPageTitle(wfMessage('friendsboard-title', $user->getName())->plain());
		$wgOut->addModuleStyles(['ext.curseprofile.profilepage.styles', 'ext.hydraCore.pagination.styles']);
		$wgOut->addModules(['ext.curseprofile.profilepage.scripts']);
		$templateManageFriends = new TemplateManageFriends;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);

		$f = new Friendship($globalId);

		$friends = $f->getFriends();
		$pagination = HydraCore::generatePaginationHtml($this->getFullTitle(), count($friends), $itemsPerPage, $start);

		$wgOut->addHTML($templateManageFriends->display($friends, $pagination, $itemsPerPage, $start));
	}
}

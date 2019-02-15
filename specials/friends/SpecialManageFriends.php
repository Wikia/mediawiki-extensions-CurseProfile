<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		CurseProfile
 * @link		https://gitlab.com/hydrawiki
 *
**/
namespace CurseProfile;

use CentralIdLookup;
use SpecialPage;
use TemplateManageFriends;
use UserNotLoggedIn;

/**
 * Special page that allows one to manage their friends.
 * In addition to listing current friends, shows pending requests, both incoming and outgoing.
 * Also allows for friend requests to be sent directly by name.
 */
class SpecialManageFriends extends SpecialPage {
	public function __construct() {
		parent::__construct('ManageFriends');
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Execute
	 *
	 * @param array $param
	 * @return void
	 */
	public function execute($param) {
		$this->setHeaders();
		$this->outputHeader();
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();

		// Fix missing or incorrect username segment in the path
		$user = $this->getUser();
		if ($user->isAnon()) {
			throw new UserNotLoggedIn('exception-nologinreturn-text', 'exception-nologin', ['Special:ManageFriends']);
		}

		$start = $wgRequest->getInt('st');
		$itemsPerPage = 25;
		$wgOut->addModuleStyles(['ext.curseprofile.profilepage.styles', 'ext.hydraCore.pagination.styles']);
		$wgOut->addModules(['ext.curseprofile.profilepage.scripts']);
		$templateManageFriends = new TemplateManageFriends;

		// $wgOut->addHTML($templateCommentBoard->header($user, $wgOut->getPageTitle()));

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);

		$f = new Friendship($globalId);

		$friends = $f->getFriends();
		$rcvd = $f->getReceivedRequests();
		$sent = $f->getSentRequests();

		$wgOut->addHTML($templateManageFriends->manage($friends, $rcvd, $sent, $itemsPerPage, $start));
	}
}

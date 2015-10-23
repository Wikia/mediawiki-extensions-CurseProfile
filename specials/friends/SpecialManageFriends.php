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
 * Special page that allows one to manage their friends.
 * In addition to listing current friends, shows pending requests, both incoming and outgoing.
 * Also allows for friend requests to be sent directly by name.
 */
class SpecialManageFriends extends \SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageFriends' );
	}

	protected function getGroupName() {
		return 'users';
	}

	public function execute( $param ) {
		$this->setHeaders();
		$this->outputHeader();
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();

		// Fix missing or incorrect username segment in the path
		$user = $this->getUser();
		if ($user->isAnon()) {
			throw new \UserNotLoggedIn('exception-nologinreturn-text', 'exception-nologin', ['Special:ManageFriends']);
		}

		// $start = $wgRequest->getInt('st');
		// $itemsPerPage = 50;
		$wgOut->addModules('ext.curseprofile.profilepage');
		$templateManageFriends = new \TemplateManageFriends;

		// $wgOut->addHTML($templateCommentBoard->header($user, $wgOut->getPageTitle()));

		$f = new Friendship($user->curse_id);

		$friends = $f->getFriends();
		$rcvd = $f->getReceivedRequests();
		$sent = $f->getSentRequests();
		// $wgOut->addModules('ext.curse.pagination');
		// $pagination = \Curse::generatePaginationHtml($total, $itemsPerPage, $start);

		$wgOut->addHTML($templateManageFriends->manage($friends, $rcvd, $sent));

		return;
	}
}

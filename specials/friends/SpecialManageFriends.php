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

	/**
	 * Return the group name for this special page.
	 *
	 * @access	protected
	 * @return	string
	 */
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

		$start = $wgRequest->getInt('st');
		$itemsPerPage = 25;
		$wgOut->addModules('ext.curseprofile.profilepage');
		$templateManageFriends = new \TemplateManageFriends;

		// $wgOut->addHTML($templateCommentBoard->header($user, $wgOut->getPageTitle()));

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);

		$f = new Friendship($globalId);

		$friends = $f->getFriends();
		$rcvd = $f->getReceivedRequests();
		$sent = $f->getSentRequests();

		$wgOut->addModules('ext.hydraCore.pagination.styles');

		$wgOut->addHTML($templateManageFriends->manage($friends, $rcvd, $sent, $itemsPerPage, $start));

		return;
	}
}

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
		$mouse = CP::loadMouse(['output' => 'mouseOutputOutput']);
		$wgOut->addModules('ext.curseprofile.profilepage');
		$mouse->output->addTemplateFolder(dirname(dirname(__DIR__)).'/templates');
		$mouse->output->loadTemplate('managefriends');

		// $wgOut->addHTML($mouse->output->commentboard->header($user, $wgOut->getPageTitle()));

		$f = new Friendship($user->curse_id);

		$friends = $f->getFriends();
		$rcvd = $f->getReceivedRequests();
		$sent = $f->getSentRequests();
		// $pagination = $mouse->output->generatePagination($total, $itemsPerPage, $start);
		// $pagination = $mouse->output->paginationTemplate($pagination);

		$wgOut->addHTML($mouse->output->managefriends->manage($friends, $rcvd, $sent));

		return;
	}
}

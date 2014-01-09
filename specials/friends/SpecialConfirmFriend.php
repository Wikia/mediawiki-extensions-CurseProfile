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

class SpecialConfirmFriend extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'ConfirmFriend' );
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

		$user = \User::newFromId($toUser);
		$friendship = new Friendship(CP::curseIDfromUserID($wgUser->getID()));
		// $wgOut->addHTML('result: <pre>'.var_export($friendship->sendRequest(CP::curseIDfromUserID($toUser)), true));
		$friendship->acceptRequest(CP::curseIDfromUserID($toUser));
		$wgOut->redirect('/User:'.urlencode($user->getName()));
		return;

		if ($wgRequest->wasPosted()) {
			//confirm form, do redirect
		}

		$this->setHeaders();

		// Can't use $this->setHeaders(); here because then it'll set the page
		// title to <removerelationship> and we don't want that, we'll be
		// messing with the page title later on in the code
		$wgOut->setArticleRelated( false );
		$wgOut->setRobotPolicy( 'noindex,nofollow' );

		$toUser = intval($toUser);
		if ($toUser < 1) {
			$wgOut->addHTML('Error, no user specified');
			return;
		}

		$user = \User::newFromId($toUser);
		$wgOut->addHTML('Confirm your friend request to '.$user->getName());
		$wgOut->addHTML('<form><input type="submit" value="Send It!"></form> <a href="">Cancel</a>');
	}
}

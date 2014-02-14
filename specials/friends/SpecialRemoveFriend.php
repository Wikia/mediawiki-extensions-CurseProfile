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

class SpecialRemoveFriend extends SpecialConfirmAction {
	public function __construct() {
		parent::__construct( 'RemoveFriend' );
	}

	protected function getConfirmMessage() {
		return wfMessage('friendrequestremove-prompt', $this->user->getName())->plain();
	}

	protected function getButtonMessage() {
		return wfMessage('removefriend')->plain();
	}

	public function execute( $param ) {
		$this->toUser = intval($param);
		if ($this->toUser < 1) {
			$this->getOutput()->addHTML('Error, no user specified');
			return;
		}
		$this->user = \User::newFromId($this->toUser);
		return parent::execute($param);
	}

	public function confirm($formData) {
		global $wgUser;
		$friendship = new Friendship(CP::curseIDfromUserID($wgUser->getID()));
		$res = $friendship->removeFriend(CP::curseIDfromUserID($this->toUser));
		if ($res) {
			return true;
		} else {
			return wfMessage('friendrequestremove-error')->plain();
		}
	}
}

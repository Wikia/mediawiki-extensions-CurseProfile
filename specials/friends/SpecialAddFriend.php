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

class SpecialAddFriend extends SpecialConfirmAction {
	public function __construct() {
		parent::__construct( 'AddFriend' );
	}

	protected function getConfirmMessage() {
		return wfMessage('friendrequestsend-prompt', $this->user->getName())->plain();
	}

	protected function getButtonMessage() {
		return wfMessage('friendrequestsend')->plain();
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
		$res = $friendship->sendRequest(CP::curseIDfromUserID($this->toUser));
		if ($res) {
			return true;
		} else {
			return wfMessage('friendrequestsend-error')->plain();
		}
	}
}

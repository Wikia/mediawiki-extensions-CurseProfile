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

class SpecialToggleProfilePreference extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'ToggleProfilePreference' );
	}

	/**
	 * Show the special page
	 */
	public function execute($subPage) {
		global $wgUser;
		$wgRequest = $this->getRequest();
		$wgOut = $this->getOutput();

		if ($wgUser->isLoggedIn()) {
			$profile = new ProfileData($wgUser->getId());
			$profile->toggleTypePref();
		}

		$this->setHeaders();

		$wgOut->redirect($wgUser->getUserPage()->getFullURL());

	}
}

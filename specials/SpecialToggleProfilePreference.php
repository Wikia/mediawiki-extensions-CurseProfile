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
		$db = $this->DB = wfGetDB(DB_MASTER);

		$profile = new ProfileData($wgUser->getId());
		$newTypePref = (int) !$profile->getTypePref();

		$this->setHeaders();

		$profile->save(['typePref'=>$newTypePref]);

		$wgOut->redirect($wgUser->getUserPage()->getFullURL());

	}
}

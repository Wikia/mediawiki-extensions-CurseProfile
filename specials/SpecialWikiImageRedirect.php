<?php
/**
 * Curse Inc.
 * Curse Profile
 * Allows deferred loading of logo images for gamepedia wikis
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		Proprietary
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

use HydraCore;
use UnlistedSpecialPage;

class SpecialWikiImageRedirect extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct('WikiImageRedirect');
	}

	/**
	 * Execute
	 *
	 * @param string $path
	 * @return void
	 */
	public function execute($path) {
		$siteKey = $this->getRequest()->getVal('md5');

		$url = HydraCore::getWikiImageUrlFromMercury($siteKey);

		if (!empty($siteKey)) {
			if (!empty($url)) {
				$this->getOutput()->redirect($url);
			} else {
				$this->getOutput()->showFileNotFoundError($siteKey);
			}
		} else {
			$this->getOutput()->showUnexpectedValueError('Site Key', $siteKey);
		}
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * Allows deferred loading of logo images for gamepedia wikis
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class SpecialWikiImageRedirect extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'WikiImageRedirect' );
	}

	public function execute( $path ) {
		$siteKey = $this->getRequest()->getVal('md5');
		$redis = \RedisCache::getClient('cache');

		$url = \HydraCore::getWikiImageUrlFromMercury($siteKey);

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

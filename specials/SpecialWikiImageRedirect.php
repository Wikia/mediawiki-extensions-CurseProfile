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
		$md5 = $this->getRequest()->getVal('md5');
		$redis = \RedisCache::getClient('cache');

		if (!empty($md5) && $redis !== false) {
			// Try to use a cached value from redis
			if ($redis->exists('wikiavatar:' . $md5)) {
				$this->getOutput()->redirect($redis->get('wikiavatar:' . $md5));
				return;
			}

			// fallback to direct lookup from the gamepedia.com api
			$result = \Http::post('http://www.gamepedia.com/api/get-avatar?apikey=***REMOVED***&wikiMd5='.urlencode($md5));
			$json = json_decode($result, true);
			if ($json && isset($json['AvatarUrl'])) {
				$json['AvatarUrl'] = str_replace('http://', 'https://', $json['AvatarUrl']); //Not a clean fix for this, but it works.
				// cache to redis
				$redis->set('wikiavatar:' . $md5, $json['AvatarUrl']);
				$redis->expire('wikiavatar:' . $md5, 86400); // discard after 24 hrs
				$this->getOutput()->redirect($json['AvatarUrl']);
			} else {
				$this->getOutput()->showFileNotFoundError($md5);
			}
		}

		$this->getOutput()->showUnexpectedValueError('Wiki Key', $md5);
	}
}

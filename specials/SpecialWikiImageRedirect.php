<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on the adoption rate of CurseProfile across hydra
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
		$mouse = CP::loadMouse(['curl' => 'mouseTransferCurl']);
		$md5 = $this->getRequest()->getVal('md5');

		// Try to use a cached value from redis
		if ($mouse->redis->exists('wikiavatar:'.$md5)) {
			$this->getOutput()->redirect($mouse->redis->get('wikiavatar:'.$md5));
			return;
		}

		// fallback to direct lookup from the gamepedia.com api
		$result = $mouse->curl->post('http://www.gamepedia.com/api/get-avatar?apikey=***REMOVED***&wikiMd5='.urlencode($md5), [], [], true);
		$json = json_decode($result, true);
		if ($json && isset($json['AvatarUrl'])) {
			// cache to redis
			$mouse->redis->set('wikiavatar:'.$md5, $json['AvatarUrl']);
			$mouse->redis->expire('wikiavatar:'.$md5, 86400); // discard after 24 hrs
			$this->getOutput()->redirect($json['AvatarUrl']);
		} else {
			$this->getOutput()->showFileNotFoundError($md5);
		}
	}
}

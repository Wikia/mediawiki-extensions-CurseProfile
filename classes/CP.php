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

/**
 * Assorted utility functions
 */
class CP {
	protected static $mouse;
	/**
	 * Initializes the mouse singleton for use anywhere
	 *
	 * @param	array	[optional] extra modules that should be loaded
	 * @param	array	[optional] extra settings to be added (ignored if mouse was previously loaded elsewhere)
	 * @return	object	mouse instance
	 */
	public static function loadMouse($extraModules=[], $extraSettings=[]) {
		if (!isset(self::$mouse)) {
			if (!defined('SITE_DIR')) {
				define('SITE_DIR', dirname(dirname(dirname(__DIR__))));
			}
			if (!defined('SITES_FOLDER')) {
				define('SITES_FOLDER', SITE_DIR.'/sites');
			}
			$settings = ['file' => SITE_DIR.'/LocalSettings.php'];
			if (!empty($extraSettings)) {
				$settings = array_merge($settings, $extraSettings);
			}
			if (!class_exists('mouseHole')) {
				require_once(SITE_DIR.'/mouse/mouse.php');
			}
			self::$mouse = \mouseHole::instance(array('config' => 'mouseConfigMediawiki', 'DB' => 'mouseDatabaseMysqli', 'redis' => 'mouseCacheRedis'), $settings);
		}
		if (!empty($extraModules)) {
			self::$mouse->loadClasses($extraModules);
		}
		return self::$mouse;
	}

	// Will this work?
	public static function setMouse($mouse) {
		self::$mouse = $mouse;
	}

	/**
	 * Returns a curse id from the local database for a given user id
	 *
	 * @param	int		user id
	 * @return	int		curse id
	 */
	public static function curseIDfromUserID($user_id) {
		self::loadMouse();
		$res = self::$mouse->DB->select([
			'select'	=> 'u.curse_id',
			'from'		=> ['user' => 'u'],
			'where'		=> 'u.user_id = '.intval($user_id),
			'limit'		=> '1',
		]);
		return self::$mouse->DB->fetch($res)['curse_id'];
	}

	/**
	 * Returns a user id from the local database for a given curse id
	 *
	 * @param	int		curse id
	 * @return	int		user id
	 */
	public static function userIDfromCurseID($curse_id) {
		self::loadMouse();
		$res = self::$mouse->DB->select([
			'select'	=> 'u.user_id',
			'from'		=> ['user' => 'u'],
			'where'		=> 'u.curse_id = '.intval($curse_id),
			'limit'		=> '1',
		]);
		return self::$mouse->DB->fetch($res)['user_id'];
	}

	/**
	 * Craetes a time tag that can be converted to a dynamic relative time
	 * after adding timeago.yarp.com to the page
	 */
	public static function timeTag($timestamp) {
		$timestamp = strtotime($timestamp);
		$iso8601 = date('c', $timestamp);
		$readableTime = date('H:i, d F Y (e)', $timestamp);
		return '<time class="timeago" datetime="'.$iso8601.'">at '.$readableTime.'</time>';
	}

	/**
	 * Returns an HTML string linking to the user page with the given ID
	 *
	 * @param	mixed	user id or user object
	 * @return	string	html anchor tag fragment
	 */
	public static function userLink($user) {
		if ($user instanceof \User) {
			$user_id = $user->getId();
		} else {
			$user_id = $user;
			$user = \User::newFromId($user);
		}
		return \Linker::linkKnown($user->getUserPage(), $user->getName()); //htmlspecialchars($user->getName())
		// return \Linker::userLink($user_id, $user->getName());
	}

	/**
	 * Returns a string URL to a png image for a gamepedia wiki
	 *
	 * @param	string	md5 site key
	 * @return	string	url path to image
	 */
	public static function getWikiImageByHash($md5) {
		// TODO: write a separate file (loaded directly by the browser) to replace this function
		// That separate file should accept an MD5 url parameter and issue a 302 redirect to the correct location
		// This function would simply change to return a string URL to that new file, ultimately
		// deferring lengthy curl lookups during main rendering when it's only needed for an image.

		$mouse = self::loadMouse(['curl' => 'mouseTransferCurl']);

		// Try to use a cached value from redis
		if ($mouse->redis->exists('wikiavatar:'.$md5)) {
			return $mouse->redis->get('wikiavatar:'.$md5);
		}

		// fallback to direct lookup
		$result = $mouse->curl->post('http://www.gamepedia.com/api/get-avatar?apikey=***REMOVED***&wikiMd5='.urlencode($md5), [], [], true);
		$json = json_decode($result, true);
		if ($json && isset($json['AvatarUrl'])) {
			// cache to redis
			$mouse->redis->set('wikiavatar:'.$md5, $json['AvatarUrl']);
			$mouse->redis->expire('wikiavatar:'.$md5, 86400); // discard after 24 hrs
			return $json['AvatarUrl'];
		} else {
			return '';
		}
	}
}

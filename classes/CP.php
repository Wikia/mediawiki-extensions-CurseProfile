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
	 * @return	mouseHole	mouse instance
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
	 * (needs some error checking)
	 *
	 * @param	int		user id
	 * @return	int		curse id
	 */
	public static function curseIDfromUserID($user_id) {
		self::loadMouse();
		$res = self::$mouse->DB->select([
			'select'	=> 'u.curse_id',
			'from'		=> ['user' => 'u'],
			'where'		=> 'user_id = '.intval($user_id),
			'limit'		=> '1'
		]);
		return self::$mouse->DB->fetch($res)['curse_id'];
	}

	/**
	 * Creates a placeholder image tag of given dimensions.
	 * {{#img: 25}} produces a 25px square image
	 * Other examples:
	 * {{#img: 25 | class="icon" style="border: 1px solid black;"}}
	 * {{#img: 25 | 50}}
	 * {{#img: 25 | 50 | class="icon" style="border: 1px solid black;"}}
	 */
	public static function placeholderImage(&$parser, $width, $height = '', $attributeString = '') {
		if (intval($height) < 1) {
			$attributeString = $height;
			$height = $width;
		}

		if (is_array($attributeString)) {
			$attributes = [];
			foreach ($attributeString as $attr => $val) {
				$attributes[] = $attr.'="'.htmlspecialchars($val, ENT_QUOTES).'"';
			}
			$attributes = implode(' ', $attributes);
		} else {
			$attributes = $attributeString;
		}

		$html = "<img src='http://placehold.it/{$width}x{$height}' $attributes/>";
		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Craetes a time tag that can be converted to a dynamic relative time
	 * after adding timeago.yarp.com to the page
	 */
	public static function timeTag($timestamp) {
		$timestamp = strtotime($timestamp);
		$iso8601 = date('c', $timestamp);
		$readableTime = date('Y-m-d H:i:s', $timestamp);
		return '<time class="timeago" datetime="'.$iso8601.'">at '.$readableTime.'</time>';
	}

	/**
	 * Returns an HTML string linking to the user page with the given ID
	 */
	public static function userLink($user_id) {
		$user = \User::newFromId($user_id);
		// return \Linker::linkKnown($user->getUserPage(), $user->getName()); //htmlspecialchars($user->getName())
		return \Linker::userLink($user_id, $user->getName());
	}
}

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
	/**
	 * The db connection override for comment reporting actions
	 * @var		object	mw DB connection
	 */
	private static $db;

	/**
	 * Overrides the MW db connections used by CurseProfile objects.
	 * Useful for operating on child wikis from master wiki context.
	 *
	 * @param	object	mw DB connection
	 */
	public static function setDb($db) {
		self::$db = $db;
	}

	/**
	 * Returns a db connection to use
	 *
	 * @param	integer	mw db id (DB_MASTER or DB_SLAVE)
	 * @return	object	mw db connection
	 */
	public static function getDb($id) {
		if (isset(self::$db)) {
			return self::$db;
		}
		return wfGetDB($id);
	}

	/**
	 * Craetes a time tag that can be converted to a dynamic relative time
	 * after adding timeago.yarp.com to the page
	 */
	public static function timeTag($timestamp, $mobile = false) {
		// quick sanity check to see if the argument might already be a unix timestamp
		if (!is_numeric($timestamp) || $timestamp < 100000 || $timestamp > 3000000000) {
			$timestamp = strtotime($timestamp);
		}
		$iso8601 = date('c', $timestamp);

		if ($mobile) {
			$readableTime = date('h:i d-t-Y (e)', $timestamp);
			return '<time class="timeago" datetime="'.$iso8601.'">'.$readableTime.'</time>';
		}

		$readableTime = date('H:i, d F Y (e)', $timestamp);
		return '<time class="timeago" datetime="'.$iso8601.'">at '.$readableTime.'</time>';
	}

	/**
	 * Returns an HTML string linking to the user page with the given ID
	 *
	 * @param	mixed	user id or user object
	 * @param	string	classes to add, if defined
	 * @return	string	html anchor tag fragment
	 */
	public static function userLink($user, $class = false) {
		if ($user instanceof \User) {
			$userId = $user->getId();
		} else {
			$userId = $user;
			$user = \User::newFromId($user);
		}
		$customAttribs = [];
		if ($class && is_string($class)) {
			$customAttribs['class'] = $class;
		}
		return \Linker::linkKnown($user->getUserPage(), $user->getName(), $customAttribs); //htmlspecialchars($user->getName())
		// return \Linker::userLink($userId, $user->getName());
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
		$wikiImageRedirect = \Title::newFromText('Special:WikiImageRedirect');
		$wikiImageRedirectURL = $wikiImageRedirect->getFullURL();
		return $wikiImageRedirectURL.'?md5='.urlencode($md5);
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile\Classes;

use MediaWiki\MediaWikiServices;
use User;

/**
 * Assorted utility functions
 */
class CP {
	/**
	 * The db connection override for comment reporting actions
	 */
	private static $db;

	/**
	 * Overrides the MW db connections used by CurseProfile objects.
	 * Useful for operating on child wikis from master wiki context.
	 *
	 * @param mixed $db mw DB connection
	 */
	public static function setDb( $db ) {
		self::$db = $db;
	}

	/**
	 * Returns a db connection to use
	 *
	 * @param int $id mw db id (DB_MASTER or DB_SLAVE)
	 * @return mixed mw db connection
	 */
	public static function getDb( $id ) {
		if ( isset( self::$db ) ) {
			return self::$db;
		}
		return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( $id );
	}

	/**
	 * Creates a time tag that can be converted to a dynamic relative time
	 * after adding timeago.yarp.com to the page
	 *
	 * @param string $timestamp
	 * @param bool $mobile
	 * @return string
	 */
	public static function timeTag( $timestamp, $mobile = false ) {
		// quick sanity check to see if the argument might already be a unix timestamp
		if ( !is_numeric( $timestamp ) || $timestamp < 100000 || $timestamp > 3000000000 ) {
			$timestamp = strtotime( $timestamp );
		}
		$iso8601 = date( 'c', $timestamp );

		if ( $mobile ) {
			$readableTime = date( 'h:i d-t-Y (e)', $timestamp );
			return '<time class="timeago" datetime="' . $iso8601 . '">' . $readableTime . '</time>';
		}

		$readableTime = date( 'H:i, d F Y (e)', $timestamp );
		return '<time class="timeago" datetime="' . $iso8601 . '">at ' . $readableTime . '</time>';
	}

	/**
	 * Returns an HTML string linking to the user page with the given ID
	 *
	 * @param mixed $user user id or user object
	 * @param string $class classes to add, if defined
	 * @return string html anchor tag fragment
	 */
	public static function userLink( $user, $class = false ) {
		if ( !$user instanceof User ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $user );
		}
		$customAttribs = [];
		if ( $class && is_string( $class ) ) {
			$customAttribs['class'] = $class;
		}
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		// htmlspecialchars($user->getName())
		return $linkRenderer->makeKnownLink( $user->getUserPage(), $user->getName(), $customAttribs );
	}
}

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
use MediaWiki\User\User;
use Wikimedia\Rdbms\IDatabase;

/**
 * Assorted utility functions
 */
class CP {
	/**
	 * Returns a db connection to use
	 *
	 * @param int $id mw db id (DB_MASTER or DB_SLAVE)
	 *
	 * @return false|IDatabase mw db connection
	 */
	public static function getDb( int $id ): false|IDatabase {
		return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( $id );
	}

	/**
	 * Creates a time tag that can be converted to a dynamic relative time
	 * after adding timeago.yarp.com to the page
	 */
	public static function timeTag( string $timestamp, bool $mobile = false ): string {
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
	 * @param int|User $user user id or user object
	 * @param false|string $class classes to add, if defined
	 *
	 * @return string html anchor tag fragment
	 */
	public static function userLink( User|int $user, false|string $class = false ): string {
		if ( !$user instanceof User ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $user );
		}
		$customAttribs = [];
		if ( $class && is_string( $class ) ) {
			$customAttribs['class'] = $class;
		}
		// htmlspecialchars($user->getName())
		return MediaWikiServices::getInstance()->getLinkRenderer()
			->makeKnownLink( $user->getUserPage(), $user->getName(), $customAttribs );
	}
}

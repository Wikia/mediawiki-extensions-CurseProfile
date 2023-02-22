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
use RequestContext;
use Title;

/**
 * A class to manage displaying a list of recent activity on a user profile
 */
class RecentActivity {
	/**
	 * Handle parser hook call
	 *
	 * @param mixed &$parser
	 * @param string $userId
	 *
	 * @return mixed
	 */
	public static function parserHook( &$parser, $userId = '' ) {
		$contextUser = RequestContext::getMain()->getUser();
		$userId = (int)$userId;
		if ( $userId < 1 ) {
			return 'Invalid user ID given';
		}
		$activity = self::fetchRecentRevisions( $userId );
		if ( count( $activity ) == 0 ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId );
			$user->load();
			return wfMessage( 'emptyactivity' )->params( $user->getName(), $contextUser->getName() )->text();
		}

		$html = '
		<ul>';
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		foreach ( $activity as $rev ) {
			$title = Title::newFromID( $rev['rev_page'] );
			if ( $title ) {
				$action = $rev['rev_parent_id'] ? 'edited' : 'created';
				$history = [
					wfMessage( 'profileactivity-' . $action )->params( $contextUser->getName() ),
					$linkRenderer->makeLink( $title ),
					self::diffHistLinks( $title, $rev ),
					CP::timeTag( $rev['rev_timestamp'] )
				];
				$history = implode( ' ', $history );
				$html .= '<li>' . $history . '</li>';
			}
		}
		$html .= '
		</ul>';

		return [ $html, 'isHTML' => true ];
	}

	/**
	 * Generates html for a link group like: (diff | hist)
	 *
	 * @param Title $title mw Title object of the page
	 * @param array $rev row from the revision table that should be diffed
	 *
	 * @return string
	 */
	public static function diffHistLinks( $title, $rev ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$html = $linkRenderer->makeLink( $title, 'diff', [], [ 'diff' => $rev['rev_id'] ] );
		$html .= ' | ';
		$html .= $linkRenderer->makeLink( $title, 'hist', [], [ 'action' => 'history' ] );
		return '(' . $html . ')';
	}

	/**
	 * Fetches 10 recent revisions authored by given user id and returns as an array
	 *
	 * @param int $userId
	 *
	 * @return array
	 */
	private static function fetchRecentRevisions( int $userId ): array {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$results = $db->select(
			[ 'revision', 'actor' ],
			[ 'rev_page', 'rev_timestamp', 'rev_id', 'rev_parent_id' ],
			[ 'actor_user' => $userId ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_timestamp DESC', 'LIMIT' => 10 ],
			[ 'actor' => [ 'JOIN', 'rev_actor = actor_id' ] ]
		);

		$rows = [];
		while ( $row = $results->fetchRow() ) {
			$rows[] = $row;
		}
		return $rows;
	}
}

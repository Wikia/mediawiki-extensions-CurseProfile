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
**/

namespace CurseProfile;

use Linker;
use Title;

/**
 * A class to manage displaying a list of recent activity on a user profile
 */
class RecentActivity {
	/**
	 * handle parser hook call
	 *
	 * @param  object &$parser
	 * @param  string $user_id
	 * @return mixed
	 */
	public static function parserHook(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$activity = self::fetchRecentRevisions($user_id);

		if (count($activity) == 0) {
			return wfMessage('emptyactivity')->plain();
		}

		$html = '
		<ul>';
		foreach ($activity as $rev) {
			$title = Title::newFromID($rev['rev_page']);
			if ($title) {
				$verb = $rev['rev_parent_id'] ? wfMessage('profileactivity-edited') : wfMessage('profileactivity-created');
				$html .= '<li>' . $verb . ' ' . Linker::link($title) . ' ' . self::diffHistLinks($title, $rev) . ' ' . CP::timeTag($rev['rev_timestamp']) . '</li>';
			}
		}
		$html .= '
		</ul>';

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Generates html for a link group like: (diff | hist)
	 *
	 * @param Title $title mw Title object of the page
	 * @param array $rev   row from the revision table that should be diffed
	 *
	 * @return string
	 */
	public static function diffHistLinks($title, $rev) {
		$html = Linker::link($title, 'diff', [], ['diff' => $rev['rev_id']]);
		$html .= ' | ';
		$html .= Linker::link($title, 'hist', [], ['action' => 'history']);
		return '(' . $html . ')';
	}

	/**
	 * Fetches 10 recent revisions authored by given user id and returns as an array
	 */
	private static function fetchRecentRevisions($user_id) {
		$db = CP::getDb(DB_MASTER);
		$results = $db->select(
			['revision'],
			['*'],
			[
				'rev_user'		=> $user_id,
				'rev_deleted'	=> 0
			],
			__METHOD__,
			[
				'ORDER BY'	=> 'rev_timestamp DESC',
				'LIMIT'		=> 10
			]
		);

		$rows = [];
		while ($row = $results->fetchRow()) {
			$rows[] = $row;
		}
		return $rows;
	}
}

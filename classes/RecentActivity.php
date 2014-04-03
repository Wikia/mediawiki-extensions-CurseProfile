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
 * A class to manage displaying a list of recent activity on a user profile
 */
class RecentActivity {
	public static function parserHook(&$parser, $user_id = '') {
		$user_id = intval($user_id);
		if ($user_id < 1) {
			return 'Invalid user ID given';
		}
		$activity = self::fetchRecentRevisions($user_id);

		if (count($activity) == 0) {
			return wfMessage('emptyactivity')->plain();
		}

		$HTML = '
		<ul>';
		foreach ($activity as $rev) {
			$title = \Title::newFromID($rev['rev_page']);
			if ($title) {
				$verb = $rev['rev_parent_id'] ? wfMessage('profileactivity-edited') : wfMessage('profileactivity-created');
				$HTML .= '<li>'.$verb.' '.\Linker::link($title).' '.self::diffHistLinks($title, $rev).' '.CP::timeTag($rev['rev_timestamp']).'</li>';
			}
		}
		$HTML .= '
		</ul>';

		return [
			$HTML,
			'isHTML' => true,
		];
	}

	/**
	 * Generates html for a link group like: (diff | hist)
	 *
	 * @param object	mw Title object of the page
	 * @param arary		row from the revision table that should be diffed
	 */
	public static function diffHistLinks($title, $rev) {
		$HTML = \Linker::link($title, 'diff', [], ['diff'=>$rev['rev_id']]);
		$HTML .= ' | ';
		$HTML .= \Linker::link($title, 'hist', [], ['action'=>'history']);
		return '('.$HTML.')';
	}

	/**
	 * Fetches 10 recent revisions authored by given user id and returns as an array
	 */
	private static function fetchRecentRevisions($user_id) {
		$mouse = CP::loadMouse();;
		$res = $mouse->DB->query("SELECT * FROM revision WHERE rev_user = $user_id AND rev_deleted = 0 ORDER BY rev_timestamp DESC LIMIT 10");
		$rows = [];
		while ($row = $mouse->DB->fetch($res)) {
			$rows[] = $row;
		}
		return $rows;
	}
}

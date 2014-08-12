<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on 
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class StatsRecache extends \SyncService {
	public function execute($args = []) {
		$db = wfGetDB(DB_SLAVE);
		$res = $db->select('user',
			['curse_id'],
			['curse_id > 0'],
			__METHOD__
		);

		while ($row = $res->fetchRow()) {
			// get primary adoption stats
			$this->users['profile'] = 5000;
			$this->users['wiki'] = 1000;

			// get friending stats
			$f = new Friendship($row['curse_id']);
			if ($f->getFriendCount()) {
				$this->friends['more'] += 1;
				$this->avgFriends[] = $f->getFriendCount();
			} else {
				$this->friends['none'] += 1;
			}

			// get customization stats
			$params = ['useroptions:'.$row['curse_id']] + ProfileData::$editProfileFields;
			$profileFields = call_user_func_array([$this->mouse->redis, 'hmget'], $params);
			if (array_filter($profileFields)) {
				$this->profileContent['filled'] += 1;
			} else {
				$this->profileContent['empty'] += 1;
			}

			$favWiki = $this->mouse->redis->hget('useroptions:'.$row['curse_id'], 'profile-favwiki');
			if ($favWiki) {
				$this->favoriteWikis[$favWiki] += 1;
			}
		}

		// compute the average
		if (count($this->avgFriends)) {
			$this->avgFriends = array_sum($this->avgFriends) / count($this->avgFriends);
		} else {
			$this->avgFriends = 'NaN';
		}

		// save results into redis for display on the stats page
		foreach (['users', 'friends', 'avgFriends', 'profileContent', 'favoriteWikis'] as $prop) {
			$this->mouse->redis->hset('profilestats', $prop, serialize($this->$prop));
		}
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on the adoption rate of CurseProfile across Hydra.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class StatsRecache extends \SyncService\Job {
	public static $forceSingleInstance = true;

	/**
	 * Migration utility function that only needs to be run once (and when redis has been emptied)
	 * Crawls all wikis and throws as many user's profile preferences into redis as possible
	 */
	public static function populateLastPref() {
		$redis = \RedisCache::getMaster();
		$sites = \DynamicSettings\Wiki::loadAll();
		foreach ($sites as $siteKey => $wiki) {
			$this->dbs[$wiki->getSiteKey()] = $wiki->getDatabaseLB();
			$wikiKeys[] = $wiki->getSiteKey();
		}
		//Add the master into the lists so it gets processed over.
		$this->dbs['master'] = \LBFactory::singleton()->getExternalLB('master');
		$wikis['master'] = 'Master Wiki';

		unset($sites);

		foreach ($wikiKeys as $dbKey) {
			try {
				$db = $this->dbs[$dbKey]->getConnection(DB_MASTER);
			} catch (\Exception $e) {
				$this->outputLine(__METHOD__." - Unable to connect to database.", time());
				continue;
			}

			$results = $db->select(
				['user_properties', 'user'],
				['up_value', 'curse_id'],
				[
					'up_property' => 'profile-pref',
					'curse_id > 0'
				],
				__METHOD__,
				[],
				[
					'user' => [
						'LEFT JOIN', 'up_user = user_id'
					]
				]
			);

			while ($row = $results->fetchRow()) {
				$redis->hSet('profilestats:lastpref', $row['curse_id'], $row['up_value']);
			}

			$db->close();
		}
	}

	/**
	 * Refreshes profile stats data. Should be run regularly (via the StatsRecacheCron.php wrapper script)
	 * Puts a serialized PHP object into redis in the following format:
	 * {
	 *   users: {
	 *     profile: int,
	 *     wiki: int
	 *   },
	 *   friends: {
	 *     none: int,
	 *     more: int
	 *   },
	 *   avgFriends: decimal,
	 *   profileContent: {
	 *     filled: int,
	 *     empty: int
	 *   },
	 *   favoriteWikis: {
	 *     md5_key: int,
	 *     md5_key: int
	 *   }
	 * }
	 */
	public function execute($args = []) {
		$db = wfGetDB(DB_SLAVE);
		$this->outputLine('Querying users from database', time());
		$res = $db->select('user',
			['curse_id'],
			['curse_id > 0'],
			__METHOD__
		);

		while ($row = $res->fetchRow()) {
			// get primary adoption stats
			$lastPref = $this->redis->hGet('profilestats:lastpref', $row['curse_id']);
			if ($lastPref || $lastPref == NULL) {
				$this->users['profile'] += 1;
			} else {
				$this->users['wiki'] += 1;
			}

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
			$profileFields = call_user_func_array([$this->redis, 'hmget'], $params);
			if (is_array($profileFields) && array_filter($profileFields)) {
				$this->profileContent['filled'] += 1;
			} else {
				$this->profileContent['empty'] += 1;
			}

			$favWiki = $this->redis->hGet('useroptions:'.$row['curse_id'], 'profile-favwiki');
			if ($favWiki) {
				$this->favoriteWikis[$favWiki] += 1;
			}
			$this->outputLine('Compiled stats for curse_id '.$row['curse_id'], time());
		}

		// compute the average
		if (count($this->avgFriends)) {
			$this->avgFriends = number_format(array_sum($this->avgFriends) / count($this->avgFriends), 2);
		} else {
			$this->avgFriends = 'NaN';
		}

		$this->outputLine('Saving results into redis', time());
		// save results into redis for display on the stats page
		foreach (['users', 'friends', 'avgFriends', 'profileContent', 'favoriteWikis'] as $prop) {
			$this->redis->hSet('profilestats', $prop, serialize($this->$prop));
		}
		$this->redis->hSet('profilestats', 'lastRunTime', serialize(time()));
		$this->outputLine('Done.', time());
	}
}

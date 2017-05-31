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
	public static $forceSingleInstance = false;

	/**
	 * Migration utility function that only needs to be run once (and when redis has been emptied)
	 * Crawls all wikis and throws as many user's profile preferences into redis as possible
	 */
	private function populateLastPref() {
		$redis = \RedisCache::getClient('cache');
		$sites = \DynamicSettings\Wiki::loadAll();
		foreach ($sites as $siteKey => $wiki) {
			$dbs[$wiki->getSiteKey()] = $wiki->getDatabaseLB();
			$wikiKeys[] = $wiki->getSiteKey();
		}
		//Add the master into the lists so it gets processed over.
		$dbs['master'] = \LBFactory::singleton()->getExternalLB('master');
		$wikis['master'] = 'Master Wiki';

		unset($sites);

		foreach ($wikiKeys as $dbKey) {
			try {
				$db = $dbs[$dbKey]->getConnection(DB_MASTER);
			} catch (\Exception $e) {
				$this->outputLine(__METHOD__." - Unable to connect to database.", time());
				continue;
			}

			$results = $db->select(
				['user_properties', 'user_global'],
				['up_value', 'global_id'],
				[
					'up_property' => 'profile-pref',
					'global_id > 0'
				],
				__METHOD__,
				[],
				[
					'user_global' => [
						'INNER JOIN', 'user_global.user_id = user_properties.up_user'
					]
				]
			);

			while ($row = $results->fetchRow()) {
				$redis->hSet('profilestats:lastpref', $row['global_id'], $row['up_value']);
			}

			$db->close();
		}
	}

	/**
	 * Refreshes profile stats data. Should be run regularly (via the StatsRecacheCron.php wrapper script)
	 * Puts a serialized PHP object into redis in the following format:
	 * {
	 *     users: {
	 *         profile: integer,
	 *         wiki: integer
	 *     },
	 *     friends: {
	 *         none: integer,
	 *         more: integer
	 *     },
	 *     avgFriends: double,
	 *     profileContent: {
	 *         profile-field: {
	 *             filled: integer,
	 *             empty: integer
	 *         }
	 *     },
	 *     favoriteWikis: {
	 *         md5_key: integer,
	 *         md5_key: integer
	 *     }
	 * }
	 */
	public function execute($args = []) {
		$redisPrefix = $this->redis->getOption(\Redis::OPT_PREFIX);
		//self::populateLastPref();

		$this->redis->del('profilestats');
$script = "local optionsKeys = redis.call('keys', '{$redisPrefix}useroptions:*')
local fields = {'".implode("', '", ProfileData::$editProfileFields)."'}
for i, k in ipairs(optionsKeys) do
	local prefs = redis.call('hmget', k, '".implode("', '", ProfileData::$editProfileFields)."')
	for index, content in ipairs(prefs) do
		if (type(content) == 'string' and string.len(content) > 0) then
			redis.call('HINCRBY', '{$redisPrefix}profilestats', fields[index], 1)
		end
	end
end";
		$redisSha = $this->redis->script('LOAD', $script);
		$this->redis->evalSha($redisSha);

		foreach (ProfileData::$editProfileFields as $field) {
			$this->profileContent[$field]['filled'] = 0;
			$this->profileContent[$field]['empty'] = 0;
		}
		$this->users = [
			'profile' => 0,
			'wiki' => 0
		];

		$db = wfGetDB(DB_MASTER);

		$where = [
			'global_id > 0'
		];

		$result = $db->select(
			['user_global'],
			['count(*) AS total'],
			$where,
			__METHOD__
		);
		$total = intval($result->fetchRow()['total']);
		$this->outputLine("Gathering statistics for {$total} users...", time());

		for ($i = 0; $i <= $total; $i = $i + 1000) {
			$start = microtime(true);
			$this->outputLine("Iteration start {$i}", time());

			$result = $db->select(
				['user_global'],
				['global_id'],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'global_id ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				// get primary adoption stats
				try {
					$lastPref = $this->redis->hGet('profilestats:lastpref', $row['global_id']);
				} catch (\Throwable $e) {
					$this->error(__METHOD__.": Caught RedisException - ".$e->getMessage());
					return;
				}
				if ($lastPref || $lastPref == NULL) {
					$this->users['profile'] += 1;
				} else {
					$this->users['wiki'] += 1;
				}

				// get friending stats
				$f = new Friendship($row['global_id']);
				$friendCount = $f->getFriendCount();
				if ($friendCount) {
					$this->friends['more'] += 1;
					$this->avgFriends[] = $friendCount;
				} else {
					$this->friends['none'] += 1;
				}

				// get customization stats
				/*try {
					$profileFields = $this->redis->hMGet('useroptions:'.$row['global_id'], ProfileData::$editProfileFields);
				} catch (\Throwable $e) {
					$this->error(__METHOD__.": Caught RedisException - ".$e->getMessage());
					return;
				}
				foreach (ProfileData::$editProfileFields as $field) {
					if ($field === 'profile-favwiki' && isset($profileFields[$field]) && !empty($profileFields[$field])) {
						if ($favWiki) {
							$this->favoriteWikis[$favWiki] += 1;
						}
						continue;
					}
					if (isset($profileFields[$field]) && !empty($profileFields[$field])) {
						$this->profileContent[$field]['filled']++;
					} else {
						$this->profileContent[$field]['empty']++;
					}
				}*/
			}
			$end = microtime(true);
			$this->outputLine($end - $start);
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
			try {
				$this->redis->hSet('profilestats', $prop, serialize($this->$prop));
			} catch (\Throwable $e) {
				$this->error(__METHOD__.": Caught RedisException - ".$e->getMessage());
				return;
			}
		}
		$this->redis->hSet('profilestats', 'lastRunTime', serialize(time()));
		$this->outputLine('Done.', time());
	}
}

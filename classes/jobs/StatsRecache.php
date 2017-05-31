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

		$profileFields = ProfileData::$editProfileFields;
		$profileFields[] = 'profile-pref';
		$this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
		$this->redis->del('profilestats');

		//General profile statistics.
		$position = null;
		$script = "local optionsKeys = ARGV
local fields = {'".implode("', '", $profileFields)."'}
local stats = {}
for index, field in ipairs(fields) do
	stats[index] = 0
end
for i, k in ipairs(optionsKeys) do
	local prefs = redis.call('hmget', k, '".implode("', '", $profileFields)."')
	for index, content in ipairs(prefs) do
		if (fields[index] == 'profile-pref') then
			if (content == nil or content == false or content == 1) then
				stats[index] = stats[index] + 1
			end
		else
			if (type(content) == 'string' and string.len(content) > 0) then
				stats[index] = stats[index] + 1
			end
		end
	end
end
for index, count in ipairs(stats) do
	redis.call('hincrby', '{$redisPrefix}profilestats', fields[index], count)
end
";
		$scriptSha = $this->redis->script('LOAD', $script);
		while ($keys = $this->redis->scan($position, $redisPrefix.'useroptions:*', 1000)) {
			if (!empty($keys)) {
				$this->redis->evalSha($scriptSha, $keys);
			}
		}

		//Friendship.
		$position = null;
		$script = "local friendships = ARGV
local hasFriend = 0
local friends = 0;
for i, k in ipairs(friendships) do
	local count = redis.call('scard', k)
	friends = friends + count
	if (count > 0) then
		hasFriend = hasFriend + 1;
	end
end
redis.call('hincrby', '{$redisPrefix}profilestats', 'has-friend', hasFriend)
local average = redis.call('hget', '{$redisPrefix}profilestats', 'average-friends')
if (average ~= false) then
	average = ((friends / hasFriend) + average) / 2
else
	average = friends / hasFriend
end
redis.call('hset', '{$redisPrefix}profilestats', 'average-friends', average)
";
		$scriptSha = $this->redis->script('LOAD', $script);
		while ($keys = $this->redis->scan($position, $redisPrefix.'friendlist:*', 1000)) {
			if (!empty($keys)) {
				$this->redis->evalSha($scriptSha, $keys);
			}
		}

		$this->outputLine('Saving results into redis', time());
		// save results into redis for display on the stats page
		foreach (['favoriteWikis'] as $prop) {
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

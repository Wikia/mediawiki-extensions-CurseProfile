<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on the adoption rate of CurseProfile across hydra
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

class StatsRecache extends \SyncService\SyncService {
	public static $forceSingleInstance = true;

	/**
	 * Migration utility function that only needs to be run once (and when redis has been emptied)
	 * Crawls all wikis and throws as many user's profile preferences into redis as possible
	 */
	public static function populateLastPref() {
		$mouse = \mouseNest::getMouse();
		$sites = \DynamicSettings\Wiki::loadAll();
		foreach ($sites as $siteKey => $wiki) {
			$database = $wiki->getDatabase();
			\mouseHole::$settings[$wiki->getSiteKey()] = [
				'server'		=> $database['db_server'],
				'port'			=> $database['db_port'],
				'database'		=> $database['db_name'],
				'user'			=> $database['db_user'],
				'pass'			=> $database['db_password'],
				'use_database'	=> false
			];
			$wiki_dbs[$wiki->getSiteKey()] = 'mouseDatabaseMysqli';
			$wikiKeys[] = $wiki->getSiteKey();
		}

		unset($sites);
		$mouse->loadClasses($wiki_dbs);

		// include the master DB for good measure
		$wikiKeys[] = 'DB';

		foreach ($wikiKeys as $dbKey) {
			try {
				$mouse->$dbKey->init();
			} catch (\Exception $e) {
				$mouse->output->sendLine(__METHOD__." - Unable to connect to database.\n", time());
				continue;
			}

			$res = $mouse->$dbKey->select([
				'select' => 'up.up_value',
				'from' => ['user_properties'=>'up'],
				'add_join' => [[
					'select' => 'u.curse_id',
					'from' => ['user'=>'u'],
					'on' => 'up.up_user = u.user_id',
					'type' => 'left'
				]],
				'where' => 'up.up_property = \'profile-pref\' AND u.curse_id > 0'
			]);

			while ($row = $mouse->$dbKey->fetch($res)) {
				$mouse->redis->hset('profilestats:lastpref', $row['curse_id'], $row['up_value']);
			}

			$mouse->$dbKey->disconnect();
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
			$lastPref = $this->mouse->redis->hget('profilestats:lastpref', $row['curse_id']);
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
			$this->mouse->redis->hset('profilestats', $prop, serialize($this->$prop));
		}
		$this->outputLine('Done.', time());
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on the adoption rate of CurseProfile across hydra
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace CurseProfile;

use DynamicSettings\Environment;
use HydraCore\SpecialPage;
use PermissionsError;
use RedisCache;
use RedisException;
use TemplateProfileStats;

class SpecialProfileStats extends SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct('ProfileStats', 'profile-stats');
	}

	/**
	 * Main Executor
	 *
	 * @access public
	 * @param  string $path unused
	 * @return void	[Outputs to screen]
	 */
	public function execute($path) {
		if (!Environment::isMasterWiki()) {
			throw new PermissionsError(null, ['cp-master-only']);
		}
		$this->setHeaders();
		$this->checkPermissions();

		$redis = RedisCache::getClient('cache');

		// Data built by StatsRecache job, refer to its contents for data format.
		try {
			$profileStats = $redis->hGetAll('profilestats');
		} catch (RedisException $e) {
			$profileStats = [];
		}

		try {
			$favoriteWikis = $redis->zRevRangeByScore('profilestats:favoritewikis', '+inf', '-inf', ['withscores' => true, 'limit' => [0, 100]]);
		} catch (RedisException $e) {
			$favoriteWikis = [];
		}

		$this->output->addHTML(TemplateProfileStats::statisticsPage($profileStats, $favoriteWikis));
	}
}

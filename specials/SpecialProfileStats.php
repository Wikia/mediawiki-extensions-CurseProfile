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

use DynamicSettings\Environment;
use PermissionsError;
use RedisCache;
use RedisException;
use TemplateProfileStats;

class SpecialProfileStats extends \HydraCore\SpecialPage {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('ProfileStats', 'profile-stats');
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function execute( $path ) {
		if (!Environment::isMasterWiki()) {
			throw new PermissionsError('cp-master-only');
		}
		$this->setHeaders();
		$this->checkPermissions();

		$redis = RedisCache::getClient('cache');

		//Data built by StatsRecache job, refer to its contents for data format.
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

		$this->output->addModuleStyles('ext.curseprofile.profilestats.styles');
		$this->output->addModules('ext.curseprofile.profilestats.scripts');
		$this->output->addHTML(TemplateProfileStats::statisticsPage($profileStats, $favoriteWikis));
	}
}

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
		if (!defined('MASTER_WIKI') || MASTER_WIKI === false) {
			throw new PermissionsError('cp-master-only');
		}
		$this->setHeaders();
		$this->checkPermissions();

		$redis = \RedisCache::getClient('cache');

		//Data built by StatsRecache job, refer to its contents for data format.
		$profileStats = $redis->hGetAll('profilestats');
		if (is_array($profileStats) && count($profileStats)) {
			foreach ($profileStats as $key => $value) {
				$profileStats[$key] = unserialize($value);
			}
		} else {
			$profileStats = [];
		}

		$this->output->addModules('ext.curseprofile.profilestats');
		$this->output->addHTML(\TemplateProfileStats::statisticsPage($profileStats));
	}
}

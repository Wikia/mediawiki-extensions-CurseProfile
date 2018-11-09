<?php
/**
 * Curse Inc.
 * Curse Profile
 * Display stats on the adoption rate of CurseProfile across hydra
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		Proprietary
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

use Maintenance;

require_once dirname(dirname(dirname(__DIR__))) . "/maintenance/Maintenance.php";

class StatsRecacheCron extends Maintenance {
	/**
	 * Main Executor
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		StatsRecache::run([], false);
	}
}

$maintClass = 'CurseProfile\StatsRecacheCron';
require_once RUN_MAINTENANCE_IF_MAIN;

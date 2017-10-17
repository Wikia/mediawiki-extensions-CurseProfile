<?php
/**
 * Curse Inc.
 * Curse Profile
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class MigrateFriendsToCheevos extends \Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Migrate friends from redis to cheevos');
	}

	/**
	 * Migrate Location Fields
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $wgRedisServers;

		$redis = RedisCache::getClient('cache');
		$keys = $redis->keys('friendlist:*');
		$prefix = $wgRedisServers['cache']['options']['prefix'];

		foreach ($keys as $dumbRedisName) {
			$far = $prefix."friendlist:";
			$actualUsableRedisKey = str_replace($prefix,'',$dumbRedisName);

			$userId = intval(str_replace($far,'',$dumbRedisName));
			$friendIds = $redis->sMembers($actualUsableRedisKey);
			foreach ($friendIds as $friend) {
				$friend = intval($friend);
				$this->output("$userId => $friend -- ");
				try {
					\Cheevos\Cheevos::createFriendRequest($userId, $friend);
					$this->output("Relationship Created");
				} catch (\Cheevos\CheevosException $e) {
					$this->output("Error");
					var_dump($e->getMessage());
				}
				$status = \Cheevos\Cheevos::getFriendStatus($userId, $friend);
				$this->output(" -- Status: ".$status['status_name']."\n");
			}
		}
	}
}

$maintClass = 'MigrateFriendsToCheevos';
require_once(RUN_MAINTENANCE_IF_MAIN);

<?php
/**
 * Curse Inc.
 * Curse Profile
 *
 * @package   CurseProfile
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class MigrateLocationField extends Maintenance {
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Migrate profile-city, profile-city, and profile-country into profile-location.');
	}

	/**
	 * Migrate Location Fields
	 *
	 * @return void
	 */
	public function execute() {
		$redis = RedisCache::getClient('cache');
		$keys = $redis->keys('useroptions:*');
		foreach ($keys as $key) {
			$key = str_replace('Hydra:', '', $key);
			list(, $globalId) = explode(':', $key);
			if ($globalId < 1) {
				continue;
			}
			$fields = $redis->hmget($key, ['profile-city', 'profile-state', 'profile-country']);
			foreach ($fields as $index => $field) {
				if (empty($field)) {
					unset($fields[$index]);
				}
			}
			if (count($fields)) {
				$location = implode(', ', $fields);
				$this->output("Updating {$globalId} location to: {$location}\n");
				$redis->hset($key, 'profile-location', $location);
			}
		}
	}
}

$maintClass = 'MigrateLocationField';
require_once RUN_MAINTENANCE_IF_MAIN;

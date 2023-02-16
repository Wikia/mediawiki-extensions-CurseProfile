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
 */

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use Cheevos\Cheevos;
use Cheevos\CheevosException;

class MigrateFriendsToCheevos extends Maintenance {
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrate friends from redis to cheevos' );
	}

	/**
	 * Migrate Location Fields
	 *
	 * @return void
	 */
	public function execute() {
		global $wgRedisServers;

		$redis = RedisCache::getClient( 'cache' );
		$keys = $redis->keys( 'friendlist:*' );
		$prefix = $wgRedisServers['cache']['options']['prefix'];

		foreach ( $keys as $dumbRedisName ) {
			$far = $prefix . "friendlist:";
			$actualUsableRedisKey = str_replace( $prefix, '', $dumbRedisName );

			$user_id = intval( str_replace( $far, '', $dumbRedisName ) );
			$friendIds = $redis->sMembers( $actualUsableRedisKey );
			foreach ( $friendIds as $friend ) {
				$friend = intval( $friend );
				$this->output( "$user_id => $friend -- " );
				try {
					Cheevos::createFriendRequest( $user_id, $friend );
					$this->output( "Relationship Created" );
				} catch ( CheevosException $e ) {
					$this->output( "Error" );
					var_dump( $e->getMessage() );
				}
				$status = Cheevos::getFriendStatus( $user_id, $friend );
				$this->output( " -- Status: " . $status['status_name'] . "\n" );
			}
		}
	}
}

$maintClass = 'MigrateFriendsToCheevos';
require_once RUN_MAINTENANCE_IF_MAIN;

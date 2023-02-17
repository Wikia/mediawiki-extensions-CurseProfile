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
use MediaWiki\MediaWikiServices;

class MigrateFriendsToCheevos extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrate friends from redis to cheevos' );
	}

	public function execute() {
		$redisServers = $this->getConfig()->get( 'RedisServers' );

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();
		$redis = $services->getService( RedisCache::class )->getConnection( 'cache' );

		$keys = $redis->keys( 'friendlist:*' );
		$prefix = $redisServers['cache']['options']['prefix'];

		foreach ( $keys as $dumbRedisName ) {
			$far = $prefix . "friendlist:";
			$actualUsableRedisKey = str_replace( $prefix, '', $dumbRedisName );

			$userId = (int)str_replace( $far, '', $dumbRedisName );
			$friendIds = $redis->sMembers( $actualUsableRedisKey );
			foreach ( $friendIds as $friend ) {
				$friend = (int)$friend;
				$this->output( "$userId => $friend -- " );
				try {
					Cheevos::createFriendRequest( $userFactory->newFromId( $userId ), $userFactory->newFromId( $friend ) );
					$this->output( "Relationship Created" );
				} catch ( CheevosException $e ) {
					$this->output( "Error\n{$e->getMessage()}\n{$e->getTraceAsString()}\n" );
				}
				$status = Cheevos::getFriendStatus( $userFactory->newFromId( $userId ), $userFactory->newFromId( $friend ) );
				$this->output( " -- Status: " . $status['status_name'] . "\n" );
			}
		}
	}
}

$maintClass = 'MigrateFriendsToCheevos';
require_once RUN_MAINTENANCE_IF_MAIN;

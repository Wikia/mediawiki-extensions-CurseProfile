<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @package   CurseProfile
 * @author    Noah Manneschmidt
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace CurseProfile;

use Maintenance;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class SetProfilePreference extends Maintenance {
	private const PREFERENCES = [
		'profile' => 1,
		'wiki' => 0,
	];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Overwrites the preference for profile vs. wiki pages for all users on a wiki' );
		$this->addArg(
			'newPref',
			'What the new user preference should be. One of: ' . implode( ', ', array_keys( self::PREFERENCES ) ),
			true
		);
	}

	public function execute() {
		$newSetting = $this->getArg();
		if ( !array_key_exists( $newSetting, self::PREFERENCES ) ) {
			$this->error(
				'Invalid new preference provided. Must be one of: ' . implode( ', ', array_keys( self::PREFERENCES ) )
			);
			return;
		}

		$onOrOff = self::PREFERENCES[$newSetting];
		$db = $this->getDB( DB_PRIMARY );

		// delete existing profile settings
		$db->delete( 'user_properties', [ 'up_property' => 'profile-pref' ], __METHOD__ );
		$this->output( "Existing user preferences have been removed.\n" );

		// lookup all user ids
		$res = $db->select( 'user', [ 'user_id' ], [], __METHOD__ );

		foreach ( $res as $row ) {
			$this->output( "Inserting preference row for user ID " . $row->user_id . "\n" );
			$db->insert(
				'user_properties',
				[
					'up_user' => $row->user_id,
					'up_property' => 'profile-pref',
					'up_value' => $onOrOff,
				],
				__METHOD__
			);
		}

		$this->output( "New user preferences have been saved.\n" );
	}
}

$maintClass = SetProfilePreference::class;
require_once RUN_MAINTENANCE_IF_MAIN;

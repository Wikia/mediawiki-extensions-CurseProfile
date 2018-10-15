<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class SetProfilePreference extends \Maintenance {
	protected static $preferences = [
		'profile' => 1,
		'wiki' => 0,
	];

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Overwrites the preference for profile vs. wiki pages for all users on a wiki');
		$this->addArg('newPref', 'What the new user preference should be. One of: '.implode(', ', array_keys(self::$preferences)), true);
	}

	public function execute() {
		$newSetting = $this->getArg();
		if (!in_array($newSetting, array_keys(self::$preferences))) {
			$this->error('Invalid new preference provided. Must be one of: '.implode(', ', array_keys(self::$preferences)));
			return;
		}

		$onOrOff = self::$preferences[$newSetting];
		$db = wfGetDB(DB_MASTER);

		// delete existing profile settings
		$db->delete('user_properties', ['up_property' => 'profile-pref'], __METHOD__);
		$this->output("Existing user preferences have been removed.\n");

		// lookup all user ids
		$res = $db->select('user', ['user_id'], [], __METHOD__);

		foreach ($res as $row) {
			$this->output("Inserting preference row for user ID ".$row->user_id."\n");
			$db->insert('user_properties', [
					'up_user' => $row->user_id,
					'up_property' => 'profile-pref',
					'up_value' => $onOrOff,
				],
				__METHOD__
			);
		}

		$this->output("New user preferences have been saved.\n");
	}
}

$maintClass = 'CurseProfile\SetProfilePreference';
require_once( RUN_MAINTENANCE_IF_MAIN );
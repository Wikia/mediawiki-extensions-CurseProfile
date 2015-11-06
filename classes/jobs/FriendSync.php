<?php
/**
 * Curse Inc.
 * Curse Profile
 * A modular, multi-featured user profile system.
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/
namespace CurseProfile;

/**
 * Syncs global data from child wikis back to the master
 */
class FriendSync extends \SyncService\Job {
	public function execute($args = []) {
		$friendship = new Friendship($args['actor']);
		return $friendship->saveToDB($args);
	}
}

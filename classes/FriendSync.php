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
class FriendSync extends \SyncService {
	public function execute($args = []) {
		CP::setMouse((object)['DB'=>$this->DB,'redis'=>$this->redis]);
		$friendship = new Friendship($args['actor']);
		return $friendship->saveToDB($args);
	}
}

<?php
/**
 * Curse Inc.
 * Curse Profile
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		Proprietary
 * @package		CurseProfile
 * @link		http://www.curse.com/
 *
**/

namespace CurseProfile\MWEcho;

use EchoEvent;
use User;

class EchoUserLocator {
	/**
	 * Locate users to notify for an event.
	 *
	 * @access	public
	 * @param	string	$event Task Performed
	 * @return	array	Array of User IDs => User objects.
	 */
	public static function getAdmins(EchoEvent $event) {
		$db = wfGetDB(DB_MASTER);

		$result = $db->select(
			['user_groups', 'user'],
			['*'],
			[
				"user_groups.ug_group"	=> 'sysop'
			],
			__METHOD__,
			['GROUP BY' => 'user.user_id'],
			[
				'user' => [
					'INNER JOIN', 'user.user_id = user_groups.ug_user'
				]
			]
		);

		$users = [];
		while ($row = $result->fetchObject()) {
			$user = User::newFromRow($row);
			if (!empty($user) && $user->getId() > 0) {
				$users[$user->getId()] = $user;
			}
		}
		return $users;
	}
}

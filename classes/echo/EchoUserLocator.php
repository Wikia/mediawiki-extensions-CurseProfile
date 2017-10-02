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

namespace CurseProfile\MWEcho;

class EchoUserLocator {
	/**
	 * Locate users to notify for an event.
	 *
	 * @access	public
	 * @param	string	Task Performed
	 * @return	array	Array of User IDs => User objects.
	 */
	static public function getAdmins(\EchoEvent $event) {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$commentModGroup = $config->get('CPCommentModGroup');

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
			$user = \User::newFromRow($row);
			if (!empty($user) && $user->getId() > 0) {
				$users[$user->getId()] = $user;
			}
		}
		return $users;
	}
}
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

/**
 * Class for reading and saving custom user-set profile data
 */
class ProfileData {
	protected $user_id;

	public function __construct($user_id) {
		$this->user_id = intval($user_id);
		if ($this->user_id < 1) {
			throw new \Exception('Invalid User ID');
		}
	}

	public function save($data) {
		$dbData = [
			'up_about' => $data['aboutme'],
			'up_location_city' => $data['city'],
			'up_location_state' => $data['state'],
			'up_location_country' => $data['country'],
			'up_steam_profile' => $data['steam_link'],
			'up_xbl_profile' => $data['xbl_link'],
			'up_psn_profile' => $data['psn_link'],
		];
		$mouse = CP::loadMouse();
		$profile_exists = $mouse->DB->selectAndFetch([
			'select' => 'count(*) as count',
			'from'   => 'user_profile',
			'where'  => 'up_user_id = '.$this->user_id
		]);
		if ($profile_exists['count']) {
			$mouse->DB->update('user_profile', $dbData, 'up_user_id = '.$this->user_id);
		} else {
			$dbData['up_user_id'] = $this->user_id;
			$mouse->DB->insert('user_profile', $dbData);
		}
	}

	public function getAboutText() {
		$mouse = CP::loadMouse();
		$profile = $mouse->DB->selectAndFetch([
			'select' => 'up_about',
			'from'   => 'user_profile',
			'where'  => 'up_user_id = '.$this->user_id
		]);
		if (!$profile) {
			return '';
		} else {
			return $profile['up_about'];
		}
	}

	public function getLocations() {
		$mouse = CP::loadMouse();
		$profile = $mouse->DB->selectAndFetch([
			'select' => 'up_location_city as city, up_location_state as state, up_location_country as country',
			'from'   => 'user_profile',
			'where'  => 'up_user_id = '.$this->user_id
		]);
		if (!$profile) {
			return [];
		} else {
			return $profile;
		}
	}

	public function getProfileLinks() {
		$mouse = CP::loadMouse();
		$profile = $mouse->DB->selectAndFetch([
			'select' => 'up_steam_profile as Steam, up_xbl_profile as "XBL", up_psn_profile as PSN',
			'from'   => 'user_profile',
			'where'  => 'up_user_id = '.$this->user_id
		]);

		if (!$profile) {
			return [];
		} else {
			// prune null and empty values
			$toRemove = [];
			foreach ($profile as $network => $link) {
				if (is_null($link) || empty($link)) {
					$toRemove[] = $network;
				}
			}
			foreach ($toRemove as $network) {
				unset($profile[$network]);
			}
			return $profile;
		}
	}
}

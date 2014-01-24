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
	/**
	 * @var		integer
	 */
	protected $user_id;

	/**
	 * Row as retrieved from the database
	 * @var		array
	 */
	protected $data;

	public function __construct($user_id) {
		$this->user_id = intval($user_id);
		if ($this->user_id < 1) {
			// if a user hasn't saved a profile yet, just use the default values
			$this->user_id = 0;
		}
	}

	protected function load() {
		if (isset($this->data)) {
			return;
		}
		$mouse = CP::loadMouse();
		$this->data = $mouse->DB->selectAndFetch([
			'select' => '*',
			'from'   => 'user_profile',
			'where'  => 'up_user_id = '.$this->user_id
		]);
	}

	/**
	 * Saves the given data
	 *
	 * @param	array	array of data using keys ('aboutme', 'fav_wiki', 'city', 'state', etc)
	 * @return	boolean	false on failure
	 */
	public function save($data) {
		$dbData = [];
		$keyMap = [
			//DB col   =>  friendly name
			'up_about' => 'aboutme',
			'up_location_city' => 'city',
			'up_location_state' => 'state',
			'up_location_country' => 'country',
			'up_steam_profile' => 'steam_link',
			'up_xbl_profile' => 'xbl_link',
			'up_psn_profile' => 'psn_link',
			'up_favorite_wiki' => 'fav_wiki',
			'up_type' => 'typePref',
		];
		foreach ($keyMap as $dbKey => $inKey) {
			if (isset($data[$inKey])) {
				$dbData[$dbKey] = $data[$inKey];
			}
		}

		$mouse = CP::loadMouse();
		$profile_exists = $mouse->DB->selectAndFetch([
			'select' => 'count(*) as count',
			'from'   => 'user_profile',
			'where'  => 'up_user_id = '.$this->user_id
		]);
		if ($profile_exists['count']) {
			// don't update the default profile
			if ($this->user_id == 0) {
				return false;
			}
			$mouse->DB->update('user_profile', $dbData, 'up_user_id = '.$this->user_id);
		} else {
			// only use default values for the default profile
			if ($this->user_id == 0) {
				$dbData = [];
			}
			$dbData['up_user_id'] = $this->user_id;
			$mouse->DB->insert('user_profile', $dbData);
		}
	}

	public function getAboutText() {
		$this->load();
		if (!$this->data) {
			return '';
		} else {
			return $this->data['up_about'];
		}
	}

	public function getLocations() {
		$this->load();
		if (!$this->data) {
			return [];
		} else {
			$profile = [
				'city' => $this->data['up_location_city'],
				'state' => $this->data['up_location_state'],
				'country' => $this->data['up_location_country'],
			];
			return array_filter($profile);
		}
	}

	public function getProfileLinks() {
		$this->load();
		if (!$this->data) {
			return [];
		} else {
			$profile = [
				'Steam' => $this->data['up_steam_profile'],
				'XBL' => $this->data['up_xbl_profile'],
				'PSN' => $this->data['up_psn_profile'],
			];
			return array_filter($profile);
		}
	}

	public function getFavoriteWikiHash() {
		$this->load();
		if (!$this->data) {
			return '';
		} else {
			return $this->data['up_favorite_wiki'];
		}
	}

	public function getFavoriteWiki() {
		$this->load();
		if (!$this->data) {
			return [];
		} else {
			$mouse = CP::loadMouse(['curl' => 'mouseTransferCurl']);
			global $wgServer;
			$jsonSites = $mouse->curl->fetch($wgServer.'/extensions/AllSites/api.php?action=siteInformation&task=getSiteStats');
			$sites = json_decode($jsonSites, true);
			if ($sites) {
				foreach ($sites['data']['wikis'] as $wiki) {
					if ($wiki['md5_key'] == $this->data['up_favorite_wiki']) {
						return $wiki;
					}
				}
			}
			return [];
		}
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 */
	public function getTypePref() {
		$this->load();
		if (!$this->data) {
			// No profile exists, use the default value for the database column
			$mouse = CP::loadMouse();
			$res = $mouse->DB->selectAndFetch([
				'select' => 'DEFAULT(up_type) as def',
				'from'   => 'user_profile',
			]);
			return $res['def'];
		} else {
			return $this->data['up_type'];
		}
	}
}

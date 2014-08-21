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
	 * @var		object
	 */
	protected $user;

	public static $editProfileFields = [
		'profile-aboutme',
		'profile-city',
		'profile-state',
		'profile-country',
		'profile-link-twitter',
		'profile-link-facebook',
		'profile-link-google',
		'profile-link-reddit',
		'profile-link-steam',
		'profile-link-xbl',
		'profile-link-psn',
		'profile-favwiki'
	];

	public function getProfilePath() {
		if (!$this->getTypePref()) {
			return "/UserProfile:".$this->user->getTitleKey();
		} else {
			return "/User:".$this->user->getTitleKey();
		}
	}

	public function getUserWikiPath() {
		if ($this->getTypePref()) {
			return "/UserWiki:".$this->user->getTitleKey();
		} else {
			return "/User:".$this->user->getTitleKey();
		}
	}

	public static function insertProfilePrefs(&$preferences) {
		$wikiOptions = [
			'---' => '',
		];
		$wikiSites = self::getWikiSites();
		if ($wikiSites) {
			foreach ($wikiSites['data']['wikis'] as $wiki) {
				if ($wiki['group_domain']) {
					$wiki['wiki_name'] = $wiki['wiki_name']. " ({$wiki['wiki_language']})";
				}
				$wikiOptions[$wiki['wiki_name']] = $wiki['md5_key'];
			}
		}
		ksort($wikiOptions);

		$preferences['profile-pref'] = [
			'type' => 'select',
			'label-message' => 'profileprefselect',
			'section' => 'personal/info/public',
			'options' => [
				wfMessage('profilepref-profile')->plain() => 1,
				wfMessage('profilepref-wiki')->plain() => 0,
			],
		];
		$preferences['profile-favwiki'] = [
			'type' => 'select',
			'label-message' => 'favoritewiki',
			'section' => 'personal/info/public',
			'options' => $wikiOptions,
		];
		$preferences['profile-aboutme'] = [
			'type' => 'textarea',
			'label-message' => 'aboutme',
			'section' => 'personal/info/public',
			'rows' => 6,
			'maxlength' => 5000,
			'placeholder' => wfMessage('aboutmeplaceholder')->plain(),
			'help-message' => 'aboutmehelp',
		];
		$preferences['profile-avatar'] = [
			'type' => 'info',
			'label-message' =>'avatar',
			'section' => 'personal/info/public',
			'default' => wfMessage('avatar-help')->parse(),
			'raw' => true,
		];
		$preferences['profile-city'] = [
			'type' => 'text',
			'label-message' => 'citylabel',
			'section' => 'personal/info/location',
		];
		$preferences['profile-state'] = [
			'type' => 'text',
			'label-message' => 'statelabel',
			'section' => 'personal/info/location',
		];
		$preferences['profile-country'] = [
			'type' => 'text',
			'label-message' => 'countrylabel',
			'section' => 'personal/info/location',
		];
		$preferences['profile-link-facebook'] = [
			'type' => 'text',
			'pattern' => 'https?://www\\.facebook\\.com/([\\w\\.]+)',
			'label-message' => 'facebooklink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('fblinkplaceholder')->plain(),
		];
		$preferences['profile-link-google'] = [
			'type' => 'text',
			'pattern' => 'https?://(plus|www)\\.google\\.com/(u/\\d/)?\\+?\\w+(/(posts|about)?)?',
			'label-message' => 'googlelink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('googlelinkplaceholder')->plain(),
		];
		$preferences['profile-link-steam'] = [
			'type' => 'text',
			'pattern' => 'https?://steamcommunity\\.com/id/([\\w-]+)/?',
			'label-message' => 'steamlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('steamlinkplaceholder')->plain(),
			'help-message' => 'profilelink-help',
		];
		$preferences['profile-link-twitter'] = [
			'type' => 'text',
			'pattern' => '@?(\\w{1,15})',
			'maxlength' => 15,
			'label-message' => 'twitterlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('twitterlinkplaceholder')->plain(),
		];
		$preferences['profile-link-reddit'] = [
			'type' => 'text',
			'pattern' => '\\w{3,20}',
			'maxlength' => 20,
			'label-message' => 'redditlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('redditlinkplaceholder')->plain(),
		];
		$preferences['profile-link-xbl'] = [
			'type' => 'text',
			'label-message' => 'xbllink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('xbllinkplaceholder')->plain(),
		];
		$preferences['profile-link-psn'] = [
			'type' => 'text',
			'label-message' => 'psnlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('psnlinkplaceholder')->plain(),
		];
	}

	public static function insertProfilePrefsDefaults(&$defaultOptions) {
		$defaultOptions['echo-subscriptions-web-friendship'] = 1;
		$defaultOptions['echo-subscriptions-email-friendship'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-comment'] = 0;

		// Allow overriding by setting the value in the global $wgDefaultUserOptions
		if (!isset($defaultOptions['profile-pref'])) {
			$defaultOptions['profile-pref'] = 1;
		}
	}

	public static function processPreferenceSave($user, &$preferences) {
		if (!empty($preferences['profile-country'])) {
			$preferences['profile-country-flag'] = \FlagFinder::getCode($preferences['profile-country']);
		} else {
			$preferences['profile-country-flag'] = '';
		}

		if (isset($preferences['profile-pref'])) {
			$mouse = \mouseNest::getMouse();
			// since we don't sync profile-pref between wikis, the best we can do for reporting adoption rate
			// is to report each individual user as using the last pref they saved on any wiki
			$mouse->redis->hset('profilestats:lastpref', $user->curse_id, $preferences['profile-pref']);
		}

		foreach(self::$editProfileFields as $field) {
			if (!empty($preferences[$field])) {
				wfRunHooks('CurseProfileEdited', [$user, $preferences]);
				break;
			}
		}
	}

	public function __construct($user_id) {
		$this->user_id = intval($user_id);
		if ($this->user_id < 1) {
			// if a user hasn't saved a profile yet, just use the default values
			$this->user_id = 0;
		}
		$this->user = \User::newFromId($user_id);
	}

	public function getAboutText() {
		return $this->user->getOption('profile-aboutme');
	}

	public function getLocations() {
		$profile = [
			'city' => $this->user->getOption('profile-city'),
			'state' => $this->user->getOption('profile-state'),
			'country' => $this->user->getOption('profile-country'),
			'country-flag' => $this->user->getOption('profile-country-flag'),
		];
		return array_filter($profile);
	}

	public function getProfileLinks() {
		$profile = [
			'Twitter' => $this->user->getOption('profile-link-twitter'),
			'Facebook' => $this->user->getOption('profile-link-facebook'),
			'Google' => $this->user->getOption('profile-link-google'),
			'Reddit' => $this->user->getOption('profile-link-reddit'),
			'Steam' => $this->user->getOption('profile-link-steam'),
			'XBL' => $this->user->getOption('profile-link-xbl'),
			'PSN' => $this->user->getOption('profile-link-psn'),
		];
		return array_filter($profile);
	}

	public function getFavoriteWikiHash() {
		return $this->user->getOption('profile-favwiki');
	}

	// TODO make this able to look up wiki info directly from the has rather than loading all site info
	public function getFavoriteWiki() {
		$sites = self::getWikiSites();
		if ($sites) {
			foreach ($sites['data']['wikis'] as $wiki) {
				if ($wiki['md5_key'] == $this->user->getOption('profile-favwiki')) {
					return $wiki;
				}
			}
		}
		return [];
	}

	public static function getWikiSites() {
		global $wgServer;
		$mouse = CP::loadMouse(['curl' => 'mouseTransferCurl']);
		$jsonSites = $mouse->curl->fetch($wgServer.'/api.php?action=allsites&do=getSiteStats&format=json');
		return json_decode($jsonSites, true);
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 */
	public function getTypePref() {
		return $this->user->getIntOption('profile-pref');
	}

	public function toggleTypePref() {
		$this->user->setOption('profile-pref', !$this->getTypePref());
		$this->user->saveSettings();
	}
}

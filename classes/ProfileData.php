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

	/**
	 * Fields in user preferences that a user can edit to earn a "customize your profile" achievement
	 * @var		array
	 */
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

	/**
	 * Returns the canonical URL path to a user's profile based on their profile preference
	 * @return string
	 */
	public function getProfilePath() {
		if (!$this->getTypePref()) {
			return "/UserProfile:".$this->user->getTitleKey();
		} else {
			return "/User:".$this->user->getTitleKey();
		}
	}

	/**
	 * Returns the canonical URL path to a user's wiki page based on their profile preference
	 * @return string
	 */
	public function getUserWikiPath() {
		if ($this->getTypePref()) {
			return "/UserWiki:".$this->user->getTitleKey();
		} else {
			return "/User:".$this->user->getTitleKey();
		}
	}

	/**
	 * Inserts curse profile fields into the user preferences form
	 * @param array of data for HTMLForm to generate the Special:Preferences form
	 */
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

	/**
	 * Adds default values for preferences added by curse profile
	 * @param array of default values
	 */
	public static function insertProfilePrefsDefaults(&$defaultOptions) {
		$defaultOptions['echo-subscriptions-web-friendship'] = 1;
		$defaultOptions['echo-subscriptions-email-friendship'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-comment'] = 1;

		// Allow overriding by setting the value in the global $wgDefaultUserOptions
		if (!isset($defaultOptions['profile-pref'])) {
			$defaultOptions['profile-pref'] = 1;
		}
	}

	/**
	 * Runs when the user saves their preferences.
	 * @param $user
	 * @param $preferences
	 */
	public static function processPreferenceSave($user, &$preferences) {
		// Try to determine what flag to display based on what they have entered as their country
		if (!empty($preferences['profile-country'])) {
			$preferences['profile-country-flag'] = \FlagFinder::getCode($preferences['profile-country']);
		} else {
			$preferences['profile-country-flag'] = '';
		}

		// save the user's preference between curse profile or user wiki into redis for our profile stats tally
		if (isset($preferences['profile-pref'])) {
			$mouse = \mouseNest::getMouse();
			// since we don't sync profile-pref between wikis, the best we can do for reporting adoption rate
			// is to report each individual user as using the last pref they saved on any wiki
			$mouse->redis->hset('profilestats:lastpref', $user->curse_id, $preferences['profile-pref']);
		}

		// run hooks on profile preferences (mostly for achievements)
		foreach(self::$editProfileFields as $field) {
			if (!empty($preferences[$field])) {
				wfRunHooks('CurseProfileEdited', [$user, $preferences]);
				break;
			}
		}
	}

	/**
	 * Create a new ProfileData instance
	 * @param $user local user ID or User instance
	 */
	public function __construct($user) {
		if (is_a($user, 'User')) {
			$this->user = $user;
			$this->user_id = $user->getId();
		} else {
			$this->user_id = intval($user);
			if ($this->user_id < 1) {
				// if a user hasn't saved a profile yet, just use the default values
				$this->user_id = 0;
			}
			$this->user = \User::newFromId($user);
		}
	}

	/**
	 * Get the user's "About Me" text
	 * @return string
	 */
	public function getAboutText() {
		return $this->user->getOption('profile-aboutme');
	}

	/**
	 * Empties the user's "About Me" text
	 */
	public function purgeAboutText() {
		$this->user->setOption('profile-aboutme', '');
		$this->user->saveSettings();
	}

	/**
	 * Returns all the user's location profile data
	 * @return array possibly including keys: city, state, country, country-flag
	 */
	public function getLocations() {
		$profile = [
			'city' => $this->user->getOption('profile-city'),
			'state' => $this->user->getOption('profile-state'),
			'country' => $this->user->getOption('profile-country'),
			'country-flag' => $this->user->getOption('profile-country-flag'),
		];
		return array_filter($profile);
	}

	/**
	 * Returns all the user's social profile links
	 * @return array possibly including keys: Twitter, Facebook, Google, Reddit, Steam, XBL, PSN
	 */
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

	/**
	 * Returns the md5_key for the wiki the user has selected as a favorite
	 * @return string
	 */
	public function getFavoriteWikiHash() {
		return $this->user->getOption('profile-favwiki');
	}

	/**
	 * Returns more complete info on the wiki chosen as the user's favorite
	 * TODO make this able to look up wiki info directly from the hash rather than sifting through all site info
	 *
	 * @return array
	 */
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

	/**
	 * Returns the decoded wiki data available by the allsites API
	 * TODO rewrite this to use a DerivativeRequest object and avoid using curl
	 *
	 * @return array
	 */
	public static function getWikiSites() {
		global $wgServer;
		$mouse = CP::loadMouse(['curl' => 'mouseTransferCurl']);
		$jsonSites = $mouse->curl->fetch($wgServer.'/api.php?action=allsites&do=getSiteStats&format=json');
		return json_decode($jsonSites, true);
	}

	/**
	 * Returns true if the profile page should be used, false if the wiki should be used
	 * @return bool
	 */
	public function getTypePref() {
		return $this->user->getIntOption('profile-pref');
	}

	/**
	 * Changes the user's profile preference to the opposite of what it was before, and saves their user preferences.
	 * @return	void
	 */
	public function toggleTypePref() {
		$this->user->setOption('profile-pref', !$this->getTypePref());
		$this->user->saveSettings();
	}
}

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
		global $wgScriptPath;
		if (!$this->getTypePref()) {
			$path = "/UserProfile:".$this->user->getTitleKey();
		} else {
			$path = "/User:".$this->user->getTitleKey();
		}
		return wfExpandUrl($wgScriptPath.$path);
	}

	/**
	 * Returns the canonical URL path to a user's wiki page based on their profile preference
	 * @return string
	 */
	public function getUserWikiPath() {
		global $wgScriptPath;
		if ($this->getTypePref()) {
			$path = "/UserWiki:".$this->user->getTitleKey();
		} else {
			$path = "/User:".$this->user->getTitleKey();
		}
		return wfExpandUrl($wgScriptPath.$path);
	}

	/**
	 * Inserts curse profile fields into the user preferences form
	 * @param array of data for HTMLForm to generate the Special:Preferences form
	 */
	public static function insertProfilePrefs(&$preferences) {
		$wikiOptions = [
			'---' => '',
		];
		foreach (self::getWikiSites() as $wiki) {
			if (isset($wiki['group_domain'])) {
				$wiki['wiki_name'] = $wiki['wiki_name']. " ({$wiki['wiki_language']})";
			}
			$wikiOptions[$wiki['wiki_name']] = $wiki['md5_key'];
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
		global $wgUser;
		if ($wgUser->isBlocked()) {
			$preferences['profile-aboutme']['help-message'] = 'aboutmehelp-blocked';
			$preferences['profile-aboutme']['disabled'] = true;
		}
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
			$redis = \RedisCache::getClient('cache');
			// since we don't sync profile-pref between wikis, the best we can do for reporting adoption rate
			// is to report each individual user as using the last pref they saved on any wiki
			$curseUser = \CurseAuthUser::getInstance($user);
			$redis->hSet('profilestats:lastpref', $curseUser->getId(), $preferences['profile-pref']);
		}

		// don't allow blocked users to change their aboutme text
		if ($user->isBlocked() && isset($preferences['profile-aboutme']) && $preferences['profile-aboutme'] != $user->getOption('profile-aboutme')) {
			$preferences['profile-aboutme'] = $user->getOption('profile-aboutme');
		}

		// run hooks on profile preferences (mostly for achievements)
		foreach(self::$editProfileFields as $field) {
			if (!empty($preferences[$field])) {
				wfRunHooks('CurseProfileEdited', [$user, $field, $preferences[$field]]);
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
	 *
	 * @access	public
	 * @return	string
	 */
	public function getAboutText() {
		return (string) $this->user->getOption('profile-aboutme');
	}

	/**
	 * Can the given user edit this profile profile?
	 *
	 * @access	public
	 * @param	object	User, the performer that needs to make changes.
	 * @return	mixed	Boolean true if allowed, otherwise error message string to display.
	 */
	public function canEdit($performer) {
		global $wgEmailAuthentication;

		if ($performer->isAllowed('profile-moderate')) {
			//Moderators can always edit a profile to remove spam.
			return true;
		}

		if ($performer->getId() !== $this->user->getId()) {
			//Users can only edit their own profiles if they are not a moderator.
			return 'no-perm-profile-moderate';
		}

		if ($wgEmailAuthentication && (!boolval($performer->getEmailAuthenticationTimestamp()) || !\Sanitizer::validateEmail($performer->getEmail()))) {
			//If email authentication is turned on and their email address is invalid then prevent editing.
			return 'email-auth-required';
		}

		return true;
	}

	/**
	 * Set the user's "About Me" text
	 *
	 * @param  string  the new text for the user's aboutme
	 * @param	object	[Optional] User who performed the action.  Null to use the current user.
	 */
	public function setAboutText($text, $performer = null) {
		$this->user->setOption('profile-aboutme', $text);
		$this->user->saveSettings();

		if ($performer === null) {
			$performer = $this->user;
		}

		self::logProfileChange('profile-aboutme', $text, $this->user, $performer);
	}

	/**
	 * Log a profile change.
	 *
	 * @access	public
	 * @param	string	Section string of the profile.  Example: profile-aboutme
	 * @param	string	Comment for the log, usually the text of the change.
	 * @param	object	User targeted for the action.
	 * @param	object	User who performed the action.  Null to use the current user.
	 * @return	void
	 */
	static public function logProfileChange($section, $comment, $target, $performer) {
		if (strlen($comment) > 140) {
			$comment = substr($comment, 0, 140)."...";
		}
		//Insert an entry into the Log.
		$log = new \LogPage('curseprofile');
		$log->addEntry(
			'profile-edited',
			\Title::newFromURL('User:'.$target->getName()),
			$comment,
			['section' => $section],
			$performer
		);
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
	 *
	 * @return array
	 */
	public function getFavoriteWiki() {
		if ($this->user->getOption('profile-favwiki')) {
			$sites = self::getWikiSites($this->user->getOption('profile-favwiki'));
			return current($sites) ? current($sites) : [];
		}
		return [];
	}

	/**
	 * Returns the decoded wiki data available in redis
	 *
	 * @access	public
	 * @param	string	$siteKey md5 key for wanted site.
	 * @return	array	Wiki data arrays.
	 */
	public static function getWikiSites($siteKey = null) {
		$redis = \RedisCache::getClient('cache');
		if ($redis === false) {
			return [];
		}
		if (is_null($siteKey)) {
			$sites = $redis->sMembers('dynamicsettings:siteHashes');
		} else {
			$sites = [$siteKey];
		}
		$ret = [];
		if (!empty($sites)) {
			foreach ($sites as $md5) {
				$data = $wikiData = $redis->hGetAll('dynamicsettings:siteInfo:'.$md5);
				if (empty($data)) {
					continue;
				}
				foreach ($data as $field => $val) {
					$data[$field] = unserialize($val);
				}
				$ret[$md5] = $data;
			}
		}
		return $ret;
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

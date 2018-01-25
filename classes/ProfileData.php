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
	 *
	 * @var		array
	 */
	static public $editProfileFields = [
		'profile-aboutme',
		'profile-favwiki',
		'profile-link-facebook',
		'profile-link-google',
		'profile-link-psn',
		'profile-link-reddit',
		'profile-link-steam',
		'profile-link-twitch',
		'profile-link-twitter',
		'profile-link-vk',
		'profile-link-xbl',
		'profile-location'
	];

	/**
	 * Return editProfileFields.
	 *
	 * @return void
	 */
	public static function getValidEditFields() {
		return self::$editProfileFields;
	}

	/**
	 * Returns the canonical URL path to a user's profile.
	 *
	 * @access	public
	 * @param	boolean	True to generate User_talk URL.
	 * @return	string
	 */
	public function getProfilePath() {
		$title = \Title::newFromText('UserProfile:'.$this->user->getTitleKey());
		return $title->getFullURL();
	}

	/**
	 * Returns the canonical URL path to a user's wiki page based on their profile preference.
	 *
	 * @access	public
	 * @param	boolean	True to generate User_talk URL.
	 * @return	string
	 */
	public function getUserPageUrl($talk = false) {
		$title = \Title::newFromText(($talk ? 'User_talk:' : 'User:').$this->user->getTitleKey());

		$arguments = [];
		if ($this->getTypePref()) {
			$arguments['profile'] = 'no';
		}
		if (!$title->isKnown()) {
			$arguments['redlink'] = 1;
		}

		return $title->getFullURL($arguments);
	}

	/**
	 * Inserts curse profile fields into the user preferences form.
	 *
	 * @access	public
	 * @param	array	Data for HTMLForm to generate the Special:Preferences form
	 * @return	void
	 */
	static public function insertProfilePrefs(&$preferences) {
		global $wgUser;
		$preferences['profile-pref'] = [
			'type' => 'select',
			'label-message' => 'profileprefselect',
			'section' => 'personal/info/public',
			'options' => [
				wfMessage('profilepref-profile')->plain() => 1,
				wfMessage('profilepref-wiki')->plain() => 0,
			],
		];
		$preferences['profile-favwiki-display'] = [
			'type' => 'text',
			'label-message' => 'favoritewiki',
			'section' => 'personal/info/public',
		];
		$preferences['profile-favwiki'] = [
			'type' => 'hidden',
			'section' => 'personal/info/public',
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
		$preferences['profile-location'] = [
			'type' => 'text',
			'label-message' => 'locationlabel',
			'section' => 'personal/info/location',
		];
		$preferences['profile-link-facebook'] = [
			'type' => 'text',
			'pattern' => 'https?://www\\.facebook\\.com/([\\w\\.]+)',
			'label-message' => 'facebooklink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('facebooklinkplaceholder')->plain(),
		];
		$preferences['profile-link-google'] = [
			'type' => 'text',
			'pattern' => 'https?://(plus|www)\\.google\\.com/(u/\\d/)?\\+?\\w+(/(posts|about)?)?',
			'label-message' => 'googlelink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('googlelinkplaceholder')->plain(),
		];
		$preferences['profile-link-reddit'] = [
			'type' => 'text',
			'pattern' => '^[\w\-_]{3,20}$',
			'maxlength' => 20,
			'label-message' => 'redditlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('redditlinkplaceholder')->plain(),
		];
		$preferences['profile-link-steam'] = [
			'type' => 'text',
			'pattern' => 'https?://steamcommunity\\.com/id/([\\w-]+)/?',
			'label-message' => 'steamlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('steamlinkplaceholder')->plain(),
			'help-message' => 'profilelink-help',
		];
		$preferences['profile-link-twitch'] = [
			'type' => 'text',
			'pattern' => '^[a-zA-Z0-9\w_]{3,24}$',
			'maxlength' => 24,
			'label-message' => 'twitchlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('twitchlinkplaceholder')->plain(),
		];
		$preferences['profile-link-twitter'] = [
			'type' => 'text',
			'pattern' => '@?(\\w{1,15})',
			'maxlength' => 15,
			'label-message' => 'twitterlink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('twitterlinkplaceholder')->plain(),
		];
		$preferences['profile-link-vk'] = [
			'type' => 'text',
			'pattern' => 'https://vk\\.com/([\\w\\.]+)',
			'label-message' => 'vklink',
			'section' => 'personal/info/profiles',
			'placeholder' => wfMessage('vklinkplaceholder')->plain(),
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
		if ($wgUser->isBlocked()) {
			foreach (self::$editProfileFields as $field) {
				$preferences[$field]['help-message'] = 'profile-blocked';
				$preferences[$field]['disabled'] = true;
			}
		}
	}

	/**
	 * Adds default values for preferences added by curse profile
	 *
	 * @access	public
	 * @param	array	Default Values
	 * @return	null
	 */
	static public function insertProfilePrefsDefaults(&$defaultOptions) {
		$defaultOptions['echo-subscriptions-web-profile-friendship'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-friendship'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-comment'] = 1;
		$defaultOptions['echo-subscriptions-web-profile-report'] = 1;
		$defaultOptions['echo-subscriptions-email-profile-report'] = 1;

		// Allow overriding by setting the value in the global $wgDefaultUserOptions
		if (!isset($defaultOptions['profile-pref'])) {
			$defaultOptions['profile-pref'] = 1;
		}
	}

	/**
	 * Runs when the user saves their preferences.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	array	User Preferences
	 * @return	null
	 */
	static public function processPreferenceSave($user, &$preferences) {
		global $wgUser;

		// save the user's preference between curse profile or user wiki into redis for our profile stats tally
		if (isset($preferences['profile-pref'])) {
			$redis = \RedisCache::getClient('cache');
			// since we don't sync profile-pref between wikis, the best we can do for reporting adoption rate
			// is to report each individual user as using the last pref they saved on any wiki
			$lookup = \CentralIdLookup::factory();
			$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
			try {
				if ($redis !== false) {
					$redis->hSet('profilestats:lastpref', $globalId, $preferences['profile-pref']);
				}
			} catch (\Throwable $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
				return '';
			}
		}

		// don't allow blocked users to change their aboutme text
		//Deep in the logic of isBlocked() it tries to call on $wgUser for some unknown reason, but $wgUser can be null.
		if ($user->isSafeToLoad() && $wgUser !== null && $user->isBlocked() && isset($preferences['profile-aboutme']) && $preferences['profile-aboutme'] != $user->getOption('profile-aboutme')) {
			$preferences['profile-aboutme'] = $user->getOption('profile-aboutme');
		}

		// run hooks on profile preferences (mostly for achievements)
		foreach (self::$editProfileFields as $field) {
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
	 * Can the given user edit this profile profile?
	 *
	 * @access	public
	 * @param	object	User, the performer that needs to make changes.
	 * @return	mixed	Boolean true if allowed, otherwise error message string to display.
	 */
	public function canEdit($performer) {
		global $wgEmailAuthentication;

		if ($performer->isBlocked()) {
			return 'profile-blocked';
		}

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
	 * Get a the profile field text.
	 *
	 * @access	public
	 * @param	string	Field Name - Examples: aboutme, location, link_twitch
	 * @return	string
	 */
	public function getField($field) {
		$field = 'profile-'.$field;
		if (!in_array($field, self::$editProfileFields)) {
			throw new \MWException(__METHOD__.': Invalid profile field.');
		}
		return (string) $this->user->getOption($field);
	}

	/**
	 * Set a profile field.
	 *
	 * @access	public
	 * @param	string	Field Name - Examples: aboutme, location, link_twitch
	 * @param	string	the new text for the user's aboutme
	 * @param	object	[Optional] User who performed the action.  Null to use the current user.
	 * @return	void
	 */
	public function setField($field, $text, $performer = null) {
		$field = 'profile-'.$field;
		if (!in_array($field, self::$editProfileFields)) {
			throw new \MWException(__METHOD__.': Invalid profile field ('.$field.').');
		}

		$this->user->setOption($field, $text);
		$this->user->saveSettings();

		if ($performer === null) {
			$performer = $this->user;
		}

		self::logProfileChange($field, $text, $this->user, $performer);
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
			\Title::newFromURL('UserProfile:'.$target->getName()),
			$comment,
			['section' => $section],
			$performer
		);
	}

	/**
	 * Returns all the user's location profile data
	 *
	 * @access	public
	 * @return	array	Possibly including key: location
	 */
	public function getLocation() {
		$profile = [
			'location' => $this->user->getOption('profile-location')
		];
		return array_filter($profile);
	}

	/**
	 * Returns all the user's social profile links
	 *
	 * @access	public
	 * @return	array	Possibly including keys: Twitter, Facebook, Google, Reddit, Steam, VK, XBL, PSN
	 */
	public function getProfileLinks() {
		$profile = [
			'Facebook' => $this->user->getOption('profile-link-facebook'),
			'Google' => $this->user->getOption('profile-link-google'),
			'PSN' => $this->user->getOption('profile-link-psn'),
			'Reddit' => $this->user->getOption('profile-link-reddit'),
			'Steam' => $this->user->getOption('profile-link-steam'),
			'Twitch' => $this->user->getOption('profile-link-twitch'),
			'Twitter' => $this->user->getOption('profile-link-twitter'),
			'VK' => $this->user->getOption('profile-link-vk'),
			'XBL' => $this->user->getOption('profile-link-xbl')
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
	 * Get information about wiki sites from Redis for searching.
	 *
	 * @access	public
	 * @param 	string	Search Term
	 * @return	array	Search Results
	 */
	public static function getWikiSitesSearch($search) {
		$redis = \RedisCache::getClient('cache');
		if ($redis === false) {
			return [];
		}

		$search = is_string($search) ? "*".mb_strtolower($search, "UTF-8")."*" : null;

		$siteKeys = [];

		$it = null;
		$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
		while (($arr_keys = $redis->hScan('dynamicsettings:siteNameKeys', $it, $search)) !== false) {
			foreach ($arr_keys as $str_field => $str_value) {
				$siteKeys[] = $str_value;
			}
			if (count($siteKeys) >= 15) {
				break;
			}
		}

		return self::getWikiSites($siteKeys);
	}

	/**
	 * Returns the decoded wiki data available in redis
	 *
	 * @access	public
	 * @param	string / array	$siteKey md5 key for wanted site, or array of keys.
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
			if (!is_array($siteKey)) {
				$sites = [$siteKey];
			} else {
				$sites = $siteKey;
			}
		}
		$ret = [];
		if (!empty($sites)) {
			foreach ($sites as $md5) {
				$data = $redis->hGetAll('dynamicsettings:siteInfo:'.$md5);
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
